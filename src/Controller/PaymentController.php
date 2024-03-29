<?php

namespace PayPal\Controller;

use Cake\Http\Exception\NotFoundException;
use Laminas\Diactoros\UriFactory;

/**
 * @property \PayPal\Model\Table\PayPalPaymentsTable PayPalPayments
 */
class PaymentController extends AppController
{

    public function initialize(): void
    {
        parent::initialize();

        $this->PayPalPayments = $this->fetchTable('PayPal.PayPalPayments');
    }

    /**
     *
     * @param int $id PayPalPayment id
     * @param bool $success
     * @param string $eRedirectUrl encrypted forwarding Url
     * @throws NotFoundException if deccryption of $eRedirectUrl fails
     */
    public function execute(int $id, bool $success, string $eRedirectUrl): void
    {
        $this->autoRender = false;
        $redirectUrl = $this->PayPalPayments->decryptRedirectUrl($eRedirectUrl, $id);
        if ($redirectUrl === false)
            throw new NotFoundException();

        if ($success == '1')
            $this->PayPalPayments->execute($id, $this->request->getQuery('PayerID'));

        $factory = new UriFactory();
        $uri = $factory->createUri($redirectUrl);

        $query = \http_build_query($this->request->getQueryParams());

        $uri = $uri->withQuery(empty($uri->getQuery())
            ? $query
            : $uri->getQuery() . '&' . $query);

        $this->redirect(strval($uri));
    }

    public function lookup(int $id): void
    {
        $this->autoRender = false;
        $this->PayPalPayments->refreshState($id);
    }
}