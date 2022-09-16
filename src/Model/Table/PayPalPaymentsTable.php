<?php

namespace PayPal\Model\Table;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\Event;
use Cake\ORM\Table;
use Cake\Routing\Router;
use Cake\Utility\Security;
use DateTime;
use PayPal\Api\Payment;
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\RedirectUrls;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RelatedResources;

class PayPalPaymentsTable extends Table
{

    public function initialize(array $config): void
    {
        $this->setTable('pay_pal_payments');
        $this->addBehavior('Timestamp');
    }

    /**
     * Workaround for buggy paypal rest api
     * @param type $transactions
     */
    protected function getRelatedResources($transactions): ?RelatedResources
    {
        $relatedResources = $transactions[0]->getRelatedResources();
        if (is_array($relatedResources))
        {
            if (sizeof($relatedResources) == 0)
                return null;
            return $relatedResources[0];
        }
        return $relatedResources;
    }

    /**
     *
     * @param Payment $payment
     */
    public function savePayment(Payment $payment, $id = null, $remittanceIdentifier = null, bool $remitted = false): EntityInterface
    {
        $paymentId = $payment->getId();

        $record = $this->findByPaymentId($paymentId)->first();
        if (empty($record) && $id != null)
        {
            $record = $this->findById($id)->first();
        }

        if (empty($record))
        {
            $record = $this->newEntity([
                'payment_id' => $paymentId,
                'remittance_identifier' => $remittanceIdentifier
            ]);
        }

        if (empty($record->payment_id))
            $record->payment_id = $paymentId;

        $record->payment_state = $payment->getState();
        if ($remitted)
            $record->remitted_moment = new DateTime('now');

        $transactions = $payment->getTransactions();

        $relatedResources = $this->getRelatedResources($transactions);

        if (!empty($relatedResources))
        {
            $sale = $relatedResources->getSale();
            if (!empty($sale))
            {
                $record->sale_state = $sale->getState();
            }
        }
        return $this->saveOrFail($record);
    }

    /**
     *
     * @param string $remittanceIdentifier
     * @param \PayPal\Api\Payment $payment
     * @throws \Exception
     */
    public function createPayment(string $remittanceIdentifier, Payment &$payment, string $okUrl, string $cancelUrl)
    {
        return $this->getConnection()->transactional(function () use ($remittanceIdentifier, &$payment, $okUrl, $cancelUrl) {
            $record = $this->newEntity([
                'remittance_identifier' => $remittanceIdentifier
            ]);

            $this->saveOrFail($record);

            $id = $record->id;

            $redirectUrls = new RedirectUrls();
            $returnUrl = Router::url('/PayPalPayment/Execute/' . $id . '/', true);
            $redirectUrls->setReturnUrl($returnUrl . '1/' . $this->encryptRedirectUrl($okUrl, $id));
            $redirectUrls->setCancelUrl($returnUrl . '0/' . $this->encryptRedirectUrl($cancelUrl, $id));

            $payment->setRedirectUrls($redirectUrls);

            $apiContext = self::getApiContext();
            $payment->create($apiContext);

            return $this->savePayment($payment, $id, $remittanceIdentifier);
        });
    }

    public function execute(int $id, ?string $payerId)
    {
        $record = $this->findById($id)->first();
        if (empty($record) || empty($payerId))
            return false;

        $apiContext = self::getApiContext();
        $remittanceIdentifier = $record->remittance_identifier;
        /* @var $ppReq Payment */
        $ppReq = $this->ApiGet($record->payment_id);

        $execution = new PaymentExecution();
        $execution->setPayerId($payerId);
        $event = new Event('PayPal.BeforePaymentExecution',
            $this, ['RemittanceIdentifier' => $remittanceIdentifier] );
        $this->getEventManager()->dispatch($event);

        $result = $event->getResult();
        if (empty($result['handled']))
            throw new PayPalCallbackException('PayPal.BeforePaymentExecution was not properly handled');

        $event = null;
        try
        {
            $ppRes = $ppReq->execute($execution, $apiContext);
            $paymentState = $ppRes->getState();
            $transactions = $ppRes->getTransactions();
            $relatedResources = $this->getRelatedResources($transactions);
            $sale = $relatedResources->getSale();
            $saleState = $sale->getState();
            $remitted = $saleState == 'completed' && $paymentState == 'approved';
            $this->savePayment($ppRes, null, $remittanceIdentifier, $remitted);
            if ($remitted)
                $event = new Event('PayPal.AfterPaymentExecution',
                    $this, ['RemittanceIdentifier' => $remittanceIdentifier]);
        }
        finally
        {
            if ($event == null)
                $event = new Event('PayPal.CancelPaymentExecution',
                    $this, ['RemittanceIdentifier' => $remittanceIdentifier]);

            $this->getEventManager()->dispatch($event);
        }
    }

    public static function getApiContext(): ApiContext
    {
        $config = Configure::read('PayPal');
        $credentials = self::getCredentials();
        $apiContext = new ApiContext(new OAuthTokenCredential($credentials['ClientId'], $credentials['ClientSecret']));
        $apiContext->setConfig($config['rest-api']);
        return $apiContext;
    }

    /**
     * @return bool|EntityInterface false, when lookup by id failed
     */
    public function refreshState(int $id)
    {
        $payment = $this->findById($id)->first();
        if (empty($payment))
            return false;

        $ppp = $this->ApiGet($payment->payment_id);

        return $this->savePayment($ppp);
    }

    public function encryptRedirectUrl(string $text, int $id)
    {
        $credentials = self::getCredentials();
        $compressed = gzcompress($text, 9);
        $key = $credentials['ClientSecret'] . $id;
        return self::base64_url_encode(Security::encrypt($compressed, $key)) ;
    }

    public function decryptRedirectUrl(string $encryptedText, int $id)
    {
        $credentials = self::getCredentials();
        $key = $credentials['ClientSecret'] . $id;
        $compressed = Security::decrypt(self::base64_url_decode($encryptedText), $key);
        return gzuncompress($compressed);
    }

    public static function base64_url_encode(string $input): string
    {
        return strtr(base64_encode($input) , '+/=', '-_,');
    }

    public static function base64_url_decode(string $input)
    {
        return base64_decode(strtr($input, '-_,', '+/='));
    }

    protected function ApiGet($paymentId): Payment
    {
        return \PayPal\Api\Payment::get($paymentId, self::getApiContext());
    }

    private static function getCredentials(): array
    {
        $config = Configure::read('PayPal');
        $mode = $config['rest-api']['mode'];
        return $config[$mode . 'Credentials'];
    }
}

class PayPalCallbackException extends \Exception
{

}