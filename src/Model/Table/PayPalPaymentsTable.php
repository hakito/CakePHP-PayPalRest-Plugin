<?php

namespace PayPal\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Table;
use Cake\Routing\Router;

use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\RedirectUrls;
use PayPal\Api\PaymentExecution;

class PayPalPaymentsTable extends Table
{

    public function initialize(array $config)
    {
        $this->setTable('PayPalPayments');
    }

    /**
     * Workaround for buggy paypal rest api
     * @param type $transactions
     * @return \PayPal\Api\RelatedResources
     */
    protected static function getRelatedResources($transactions)
    {
        $relatedResources = $transactions[0]->getRelatedResources();
        return is_array($relatedResources) ? $relatedResources[0] : $relatedResources;
    }

    /**
     *
     * @param \PayPal\Api\Payment $payment
     */
    public function savePayment($payment, $id = null)
    {
        $paymentId = $payment->getId();

        $record = $this->findByPaymentId($paymentId)->first();
        if (empty($record) && $id != null)
        {
            $record = $this->findById($id)->first();
        }

        if (empty($record))
        {
            $record = new \Cake\ORM\Entity();
        }

        if (empty($record->payment_id))
            $record->payment_id = $paymentId;

        $record->payment_state = $payment->getState();

        /** @var \PayPal\Api\Transaction */
        $transactions = $payment->getTransactions();

        /** @var \PayPal\Api\RelatedResources Description */
        $relatedResources = $this->getRelatedResources($transactions);

        if (!empty($relatedResources))
        {
            $sale = $relatedResources->getSale();
            if (!empty($sale))
            {
                $record->sale_state = $sale->getState();
            }
        }
        return $this->save($record);
    }

    /**
     *
     * @param string $remittanceIdentifier
     * @param \PayPal\Api\Payment $payment
     * @throws \Exception
     */
    public function createPayment($remittanceIdentifier, &$payment, $okUrl, $cancelUrl)
    {
        $config = Configure::read('PayPalPlugin');

        return $this->getConnection()->transactional(function () use ($remittanceIdentifier, &$payment, $okUrl, $cancelUrl) {
            $record = new \Cake\ORM\Entity();
            $record->remittance_identifier = $remittanceIdentifier;

            if (!$this->save($record))
                return false;

            $id = $record->id;

            $redirectUrls = new RedirectUrls();
            $returnUrl = Router::url('/PayPalPayment/Execute/' . $id . '/', true);
            $redirectUrls->setReturnUrl($returnUrl . '1/' . $this->encryptRedirectUrl($okUrl, $id));
            $redirectUrls->setCancelUrl($returnUrl . '0/' . $this->encryptRedirectUrl($cancelUrl, $id));

            $payment->setRedirectUrls($redirectUrls);

            $apiContext = self::getApiContext();
            $payment->create($apiContext);

            return $this->savePayment($payment, $id);
        });
    }

    public function execute($id)
    {
        $record = $this->findById($id)->first();
        if (empty($record))
            return false;

        $apiContext = self::getApiContext();
        $remittanceIdentifier = $record->remittance_identifier;
        /* @var $ppReq Payment */
        $ppReq = $this->ApiGet($record->payment_id);

        $execution = new PaymentExecution();
        $execution->setPayerId($_GET['PayerID']);
        if (!$this->beforePayPalPaymentExecution($remittanceIdentifier))
            throw new PayPalCallbackException('beforePayPalPaymentExecution did not return true');

        try
        {
            $ppRes = $ppReq->execute($execution);
            $paymentState = $ppRes->getState();
            $transactions = $ppRes->getTransactions();
            $relatedResources = $this->getRelatedResources($transactions);
            $sale = $relatedResources->getSale();
            $saleState = $sale->getState();
        }
        catch (\Exception $e)
        {
            $this->cancelPayPalPaymentExecution($remittanceIdentifier);
            throw $e;
        }

        if ($saleState == 'completed' && $paymentState == 'approved')
            $this->afterPayPalPaymentExecution($remittanceIdentifier);
        else
            $this->cancelPayPalPaymentExecution($remittanceIdentifier);

        $this->savePayment($ppRes);
    }

    public static function getApiContext()
    {
        $config = Configure::read('PayPal');
        $mode = $config['rest-api']['mode'];
        $credentials = $config[$mode . 'Credentials'];
        $apiContext = new ApiContext(new OAuthTokenCredential($credentials['ClientId'], $credentials['ClientSecret']));
        $apiContext->setConfig($config['rest-api']);
        return $apiContext;
    }

    public function refreshState($id)
    {
        $payment = $this->findById($id)->first();
        if (empty($payment))
            return false;

        $ppp = $this->ApiGet($payment->payment_id);

        return $this->savePayment($ppp);
    }

    public static function setNextCheck($seconds = 0)
    {
        $newTime = time() + $seconds;
        $storedTime = self::getStoredTime();
        if ($newTime < $storedTime)
        {
            return self::setStoredTime($newTime);
        }
        return false;
    }

    public static function getStoredTime()
    {
        $config = Configure::read('PayPal');
        $filename = $config['checkFile'];
        if (!file_exists($filename))
        {
            file_put_contents($filename, 0);
            return 0;
        }

        return file_get_contents($filename);
    }

    public static function setStoredTime($time)
    {
        $config = Configure::read('PayPal');
        $filename = $config['checkFile'];
        return file_put_contents($filename, $time);
    }

    public function encryptRedirectUrl($text, $id)
    {
        $password = substr(Configure::read('Security.salt'), 0, 10) . $id;
		$vi = substr(Configure::read('Security.salt'), -16);
        $compressed = gzcompress($text, 9);
        return $this->base64_url_encode(openssl_encrypt($compressed, 'aes128', $password, true, $vi)) ;
    }

    public function decryptRedirectUrl($encryptedText, $id)
    {
        $password = substr(Configure::read('Security.salt'), 0, 10) . $id;
		$vi = substr(Configure::read('Security.salt'), -16);

        $compressed = openssl_decrypt($this->base64_url_decode($encryptedText), 'aes128', $password, true, $vi);
        return gzuncompress($compressed);
    }

    public function base64_url_encode($input)
    {
        return strtr(base64_encode($input) , '+/=', '-_,');
    }

    public function base64_url_decode($input)
    {
        return base64_decode(strtr($input, '-_,', '+/='));
    }

    protected function ApiGet($paymentId)
    {
        return \PayPal\Api\Payment::get($paymentId, self::getApiContext());
    }
}

class PayPalCallbackException extends \Exception
{

}