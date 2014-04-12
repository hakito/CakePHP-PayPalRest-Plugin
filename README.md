CakePHP-PayPalRest-Plugin
=========================

Simple PayPal plugin for CakePHP using the REST api.

Prerequisites
-------------
Download the plugin to your app/Plugin directory. This plugin requires the PayPal REST api.
You can use composer to get the REST api.

```json
{
    "require": {
        "paypal/rest-api-sdk-php" : "0.7.*",
    },
    "config": {
        "vendor-dir": "Vendor/"
    }
}
```

Configuration
-------------

You can find a sample configuration in Config/bootstrap.php. Just override the settings in your own bootstrap.php.

Usage
-----

Following is the minimal set to start a payment request:

```php
class OrdersController extends AppController {
    public $components = array('PayPal.PayPal');
    
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
        $okUrl =  Router::url('/paymentOk', true);
        
        // Url the client is redirected to whe PayPal payment fails or was cancelled
        $nOkUrl = Router::url('/paymentFailed', true);
        
        return $this->PayPal->PaymentRedirect($order['id'], $okUrl, $nOkUrl);    
    }
}
```

To receive the payment notifications in your app the Plugin needs 3 functions available in your AppModel.php
```php

    public function beforePayPalPaymentExecution($orderId)
    {
        // Will be called just after PayPal redirects the customer
        // back to your site. (You could begin a transaction here)
        // True is always expected as return value, otherwise the plugin
        // will throw an exception
        return true; 
    }

    public function cancelPayPalPaymentExecution($orderId)
    {
        // Will be called when the REST api call fails or
        // the saleState != 'completed' or paymentState != 'approved'
        // (You could rollback a transaction here)
    }

    public function afterPayPalPaymentExecution($orderId)
    {
        // Will be called after the REST api call
        // and only if the saleState == 'completed' and paymentState == 'approved'
        // (You could commit a transaction here)
    }

```

Remarks
-------

The current implementation does not support automatic handling payments in pending state. 

Donate
------

Any donation is welcome

* PayPal: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RLE88DG8CSVUE
* Bitcoin: 1QHLTMZDwTJqUK9VZWa1RKtPCHnT7wTu3q
