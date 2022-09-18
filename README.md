[![Build Status](https://app.travis-ci.com/hakito/CakePHP-PayPalRest-Plugin.svg?branch=master)](https://app.travis-ci.com/hakito/CakePHP-PayPalRest-Plugin)
[![Coverage Status](https://coveralls.io/repos/github/hakito/CakePHP-PayPalRest-Plugin/badge.svg?branch=master)](https://coveralls.io/github/hakito/CakePHP-PayPalRest-Plugin?branch=master)
[![Latest Stable Version](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/v/stable.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin) [![Total Downloads](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/downloads.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin) [![Latest Unstable Version](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/v/unstable.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin) [![License](https://poser.pugx.org/hakito/cakephp-paypal-rest-plugin/license.svg)](https://packagist.org/packages/hakito/cakephp-paypal-rest-plugin)

# CakePHP-PayPalRest-Plugin

Simple PayPal plugin for CakePHP 4.x using the [REST api (v1)](https://developer.paypal.com/docs/api/payments/v1/).

## Installation

If you are using composer simply add it with:

```bash
composer require hakito/cakephp-paypal-rest-plugin
```

## Load the plugin

Load the plugin in your bootstrap:

```php
public function bootstrap()
{
    // Call parent to load bootstrap from files.
    parent::bootstrap();

    $this->addPlugin(\PayPal\Plugin::class, ['routes' => true]);
}
```

## Creating tables

Create the database pay_pal_payments table with the following command:

```bash
bin/cake migrations migrate -p PayPal
```

## Configuration

You can find a sample configuration in tests/config/PayPal.php. Just override the settings in your own bootstrap.php.

## Usage

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

$payPalPayments = TableRegistry::getTableLocator()->get('PayPal.PayPalPayments');
$eventManager = $payPalPayments->getEventManager();
$eventManager->setEventList(new EventList());

// Will be called just after PayPal redirects the customer
// back to your site. (You could start a transaction here)
$eventManager->on('PayPal.BeforePaymentExecution',
function($event, $remittanceIdentifier)
{
    // Handled is expected to be set to TRUE, otherwise the plugin
    // will throw an exception
    return ['handled' => true];
});

// Will be called when the REST api call fails or
// the saleState != 'completed' or paymentState != 'approved'
// (You could rollback a transaction here)
$eventManager->on('PayPal.CancelPaymentExecution',
function($event, $remittanceIdentifier) {});

// Will be called after the REST api call
// and only if the saleState == 'completed' and paymentState == 'approved'
// (You could commit a transaction here)
$eventManager->on('PayPal.AfterPaymentExecution',
function($event, $remittanceIdentifier) {});

```

To issue a full refund lookup the payment and issue the refund.

```php

$payPalPayment = $payPalPayments->findByRemittanceIdentifier($remittanceIdentifier);
$payPalPayments->refundPayment($payPalPayment);

```

## Remarks

The current implementation does not support automatic handling payments in pending state.
