[![Build Status](https://travis-ci.org/hakito/CakePHP-PayPalRest-Plugin.svg?branch=master)](https://travis-ci.org/hakito/CakePHP-PayPalRest-Plugin)
[![Coverage Status](https://coveralls.io/repos/github/hakito/CakePHP-PayPalRest-Plugin/badge.svg?branch=master)](https://coveralls.io/github/hakito/CakePHP-PayPalRest-Plugin?branch=master)
[![Latest Stable Version](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/v/stable.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin) [![Total Downloads](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/downloads.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin) [![Latest Unstable Version](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/v/unstable.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin) [![License](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/license.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin)

CakePHP-PayPalRest-Plugin
=========================

Simple PayPal plugin for CakePHP using the REST api.

Installation
------------

If you are using composer simply add it with:

```bash
composer require hakito/cakephp-paypal-rest-plugin
```

Creating tables
---------------

Create the database PayPalPayments table with the following command:

```bash
bin/cake migrations migrate -p PayPal
```

Configuration
-------------

You can find a sample configuration in tests/config/PayPal.php. Just override the settings in your own bootstrap.php.

Usage
-----

Following is the minimal set to start a payment request:

```php
class OrdersController extends AppController {

    public function initialize()
    {
        parent::initialize();
        $this->loadComponent('PayPal.PayPal', []);
    }

    public yourPaymentAction($order) {
        foreach ($order['OrderedItem'] as $orderedItem)
        {
            $quantity = $orderedItem['quantity'];
            $price = $orderedItem['price'];
            $itemName = $orderedItem['name'];
            $itemId = $orderedItem['id'];

            // money values are always integer values in cents
            $this->PayPal->AddArticle($itemName, $quantity, $price, $itemId);
        }

        // optional shipping fee
        $this->PayPal->Shipping = 123; // money values are always integer values in cents

        // Url the client is redirected to when PayPal payment is performed successfully
        // NOTE: This does not mean that the payment is COMPLETE.
        $okUrl = Router::url('/paymentOk', true);

        // Url the client is redirected to whe PayPal payment fails or was cancelled
        $nOkUrl = Router::url('/paymentFailed', true);

        return $this->PayPal->PaymentRedirect($order['id'], $okUrl, $nOkUrl);
    }
}
```

To receive the payment notifications in your app the Plugin expects 3 event handlers

```php

$eventManager = TableRegistry::getTableLocator()->get('PayPal.PayPalPayments')->getEventManager();
$eventManager->setEventList(new EventList());

// Will be called just after PayPal redirects the customer
// back to your site. (You could start a transaction here)
$eventManager->on('PayPal.Model.PayPalPayments.BeforePaymentExecution',
function($event, $remittanceIdentifier, &$handled)
{
    // Handled is expected to be set to TRUE, otherwise the plugin
    // will throw an exception
    $handled = true;
});

// Will be called when the REST api call fails or
// the saleState != 'completed' or paymentState != 'approved'
// (You could rollback a transaction here)
$eventManager->on('PayPal.Model.PayPalPayments.CancelPaymentExecution',
function($event, $remittanceIdentifier) {});

// Will be called after the REST api call
// and only if the saleState == 'completed' and paymentState == 'approved'
// (You could commit a transaction here)
$eventManager->on('PayPal.Model.PayPalPayments.AfterPaymentExecution',
function($event, $remittanceIdentifier) {});

```

Remarks
-------

The current implementation does not support automatic handling payments in pending state.
