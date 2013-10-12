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
        $redirectUrl = $this->PayPalPayment->decryptRedirectUrl($id, $eRedirectUrl);
        if ($redirectUrl === false)
            throw new NotFoundException();

        $this->PayPalPayment->setNextCheck();
        if (strpos($redirectUrl, '?') < 0)
        {
            $redirectUrl .= '?';
        }

        $redirectUrl .= $_SERVER['QUERY_STRING'];
        $this->redirect($redirectUrl);
    }
}