<?php

namespace PayPal\Controller;

use Cake\Http\Exception\NotFoundException;
use Laminas\Diactoros\UriFactory;

class PaymentController extends AppController
{

    public function initialize(): void
    {
        parent::initialize();

        $this->loadModel('PayPal.PayPalPayments');
    }

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
        $redirectUrl = $this->PayPalPayments->decryptRedirectUrl($eRedirectUrl, $id);
        if ($redirectUrl === false)
            throw new NotFoundException();

        if ($success == '1')
            $this->PayPalPayments->execute($id);

        $factory = new UriFactory();
        $uri = $factory->createUri($redirectUrl);

        $query = \http_build_query($this->request->getQueryParams());

        $uri = $uri->withQuery(empty($uri->getQuery())
            ? $query
            : $uri->getQuery() . '&' . $query);

        $this->redirect(strval($uri));
    }

    public function lookup($id)
    {
        $this->autoRender = false;
        $this->PayPalPayments->refreshState($id);
    }
}