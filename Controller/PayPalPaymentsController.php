<?php

class PayPalPaymentsController extends Controller
{

    /**
     *
     * @param int $id PayPalPayment id
     * @param string $type Success or Cancel
     */
    public function execute($id, $eRedirectUrl)
    {
        $this->autoRender = false;
        $redirectUrl = $this->PayPalPayment->decryptRedirectUrl($eRedirectUrl, $id);
        if ($redirectUrl === false)
            throw new NotFoundException();

        $this->PayPalPayment->execute($id);
        $this->PayPalPayment->setNextCheck();
        if (strpos($redirectUrl, '?') === false)
        {
            $redirectUrl .= '?';
        }

        $redirectUrl .= $_SERVER['QUERY_STRING'];
        $this->redirect($redirectUrl);
    }

    public function lookup($id)
    {
        $this->autoRender = false;
        $this->PayPalPayment->refreshState($id);
    }
}