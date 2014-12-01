<?php

App::uses('AppModel', 'Model');
use PayPal\Rest\ApiContext;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Api\RedirectUrls;
use PayPal\Api\PaymentExecution;

class PayPalPayment extends AppModel
{

    public $useTable = 'PayPalPayments';

    /**
     * Workaround for buggy paypal rest api
     * @param type $transactions
     * @return PayPal\Api\RelatedResources
     */
    private function getRelatedResources($transactions)
    {
        $relatedResources = $transactions[0]->getRelatedResources();
        return is_array($relatedResources) ? $relatedResources[0] : $relatedResources;
    }

    /**
     *
     * @param PayPal\Api\Payment $payment
     */
    public function savePayment($payment, $id = null)
    {
        $paymentId = $payment->getId();

        $record = $this->findByPaymentId($paymentId);
        if (empty($record) && $id != null)
        {
            $record = $this->findById($id);
        }

        if (empty($record))
        {
            $this->create();
            $record = array('PayPalPayment');
        }

        $data = &$record['PayPalPayment'];
        if (empty($data['payment_id']))
            $data['payment_id'] = $paymentId;
        
        $data['payment_state'] = $payment->getState();

        /** @var \PayPal\Api\Transaction */
        $transactions = $payment->getTransactions();

        /** @var PayPal\Api\RelatedResources Description */
        $relatedResources = $this->getRelatedResources($transactions);

        if (!empty($relatedResources))
        {
            $sale = $relatedResources->getSale();
            if (!empty($sale))
            {
                $data['sale_state'] = $sale->getState();
            }
        }
        return $this->save($record);
    }

    /**
     *
     * @param string $remittanceIdentifier
     * @param \PayPal\Api\Payment $payment
     * @throws Exception
     */
    public function createPayment($remittanceIdentifier, &$payment, $okUrl, $cancelUrl)
    {
        $config = Configure::read('PayPalPlugin');

        $dataSource = $this->getDataSource();
        $dataSource->begin();

        try
        {
            $this->create();
            if (!$this->save(array('PayPalPayment' => array('remittance_identifier' => $remittanceIdentifier))))
            {
                $dataSource->rollback();
                return false;
            }

            $id = $this->getInsertID();

            $redirectUrls = new RedirectUrls();
            $returnUrl = Router::url('/PayPalPayment/Execute/' . $id . '/', true);
            $redirectUrls->setReturn_url($returnUrl . '1/' . $this->encryptRedirectUrl($okUrl, $id));
            $redirectUrls->setCancel_url($returnUrl . '0/' . $this->encryptRedirectUrl($cancelUrl, $id));

            $payment->setRedirectUrls($redirectUrls);

            $apiContext = $this->getApiContext();
            $payment->create($apiContext);

            if (!$this->savePayment($payment, $id))
            {
                $dataSource->rollback();
                return false;
            }
        }
        catch (Exception $e)
        {
            $dataSource->rollback();
            throw $e;
        }

        return $dataSource->commit();
    }

    public function execute($id)
    {
        $record = $this->findById($id);
        if (empty($record['PayPalPayment']))
            return false;
        $apiContext = $this->getApiContext();
        $remittanceIdentifier = $record['PayPalPayment']['remittance_identifier'];
        $ppReq = \PayPal\Api\Payment::get($record['PayPalPayment']['payment_id'], $apiContext);        
                
        $execution = new PaymentExecution();
        $execution->setPayer_id($_GET['PayerID']);
        if (!$this->beforePayPalPaymentExecution($remittanceIdentifier))
            throw new PayPalCallbackException('beforePayPalPaymentExecution did not return true');

        try
        {
            $ppRes = $ppReq->execute($execution, $apiContext);
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

    private function getApiContext()
    {
        $config = Configure::read('PayPalPlugin');
        $mode = $config['rest-api']['mode'];
        $credentials = $config[$mode . 'Credentials'];
        $apiContext = new ApiContext(new OAuthTokenCredential($credentials['ClientId'], $credentials['ClientSecret']));
        $apiContext->setConfig($config['rest-api']);
        return $apiContext;
    }

    public function refreshState($id)
    {
        $record = $this->findById($id);
        if (empty($record['PayPalPayment']))
            return false;
        $payment = $record['PayPalPayment'];
        $ppp = \PayPal\Api\Payment::get($payment['payment_id'], $this->getApiContext());

        debug($ppp->toArray());
        return $this->savePayment($ppp);
    }

    public function setNextCheck($seconds = 0)
    {
        $newTime = time() + $seconds;
        $storedTime = $this->getStoredTime();
        if ($newTime < $storedTime)
        {
            return $this->setStoredTime($newTime);
        }
        return false;
    }
    
    public function getStoredTime()
    {
        $config = Configure::read('PayPalPlugin');
        $filename = $config['checkFile'];
        if (!file_exists($filename))
        {
            file_put_contents($filename, 0);
            return 0;
        }        
    }
    
    public function setStoredTime($time)
    {
        $config = Configure::read('PayPalPlugin');
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
}

class PayPalCallbackException extends Exception
{

}