<?php

class PayPalPaymentsController extends Controller
{

    /**
     *
     * @param int $id PayPalPayment id
     * @param bool $success
     * @param string $eRedirectUrl encrypted forwarding Url
     * @throws NotFoundException if deccryption of $eRedirectUrl fails
     */
    public function execute($id, $success, $eRedirectUrl)
    {
        $this->autoRender = false;
        $redirectUrl = $this->PayPalPayment->decryptRedirectUrl($eRedirectUrl, $id);
        if ($redirectUrl === false)
            throw new NotFoundException();

        if ($success == '1')
            $this->PayPalPayment->execute($id);
        
        $this->PayPalPayment->setNextCheck();

        if (strpos($redirectUrl, '?') === false)        
            $redirectUrl .= '?';        

        $redirectUrl .= $_SERVER['QUERY_STRING'];
        $this->redirect($redirectUrl);
    }

    public function lookup($id)
    {
        $this->autoRender = false;
        $this->PayPalPayment->refreshState($id);
    }
}