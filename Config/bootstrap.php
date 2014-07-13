<?php
/**
 * BEGIN PayPalPlugin Configuration
 * Use these settings to set defaults for the PayPal component.
 *
 * put this code into your bootstrap.php, so you can override settings.
 */
if (is_null(Configure::read('PayPalPlugin'))) {
	Configure::write('PayPalPlugin', array(
        'sdkLoader' => ROOT . DS . 'Vendor' . DS . 'autoload.php', // Path to autoloader for SDK autoloader
        'currency' => 'EUR',
        'tax' => '0.2',
        'checkFile' => TMP . 'next_paypal_check.txt',
        'liveCredentials' => array(
            'ClientId' => 'clientId',    // client id obtained from the developer portal
            'ClientSecret' => 'clientSecret' // client secret obtained from the developer portal
        ),
         'sandboxCredentials' => array(
            'ClientId' => 'EBWKjlELKMYqRNQ6sYvFo64FtaRLRR5BdHEESmha49TM',    // client id obtained from the developer portal
            'ClientSecret' => 'EO422dn3gQLgDbuwqTjzrFgFtaRLRR5BdHEESmha49TM' // client secret obtained from the developer portal
        ),
        'rest-api' => array(
            'mode' => 'sandbox',    // can be set to sandbox / live
            'http.ConnectionTimeOut' => '30',
            'http.Retry' => '1',
            //'http.Proxy'='http://[username:password]@hostname[:port][/path]',
            'log.LogEnabled' => TRUE,
            'log.FileName' => LOGS . 'PayPal.log',
            'log.LogLevel' => 'FINE' // FINE, INFO, WARN or ERROR
         ),
        'conditions' => array(
            'fee' => 35,              // paypal fixed fee in cents
            'fee_relative' => '0.034' // relative paypal fee
        )
    ));
}

/** END PayPalPlugin Configuration */


$paypal_settings = Configure::read('PayPalPlugin');
require_once $paypal_settings['sdkLoader'];
unset ($paypal_settings);
