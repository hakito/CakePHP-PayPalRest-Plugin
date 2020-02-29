<?php
return [
    'PayPal' => [
        'currency' => 'EUR',
        'tax' => '0.2',
        'liveCredentials' => [
            'ClientId' => 'clientId',    // client id obtained from the developer portal
            'ClientSecret' => 'clientSecret' // client secret obtained from the developer portal
        ],
         'sandboxCredentials' => array(
            'ClientId' => 'EBWKjlELKMYqRNQ6sYvFo64FtaRLRR5BdHEESmha49TM',    // client id obtained from the developer portal
            'ClientSecret' => 'EO422dn3gQLgDbuwqTjzrFgFtaRLRR5BdHEESmha49TM' // client secret obtained from the developer portal
        ),
        'rest-api' => [
            'mode' => 'sandbox',    // can be set to sandbox / live
            'http.ConnectionTimeOut' => '30',
            'http.Retry' => '1',
            //'http.Proxy'='http://[username:password]@hostname[:port][/path]',
            'log.LogEnabled' => TRUE,
            'log.FileName' => LOGS . 'PayPal.log',
            'log.LogLevel' => 'FINE' // FINE, INFO, WARN or ERROR
         ],
        'conditions' => [
            'fee' => 35,              // paypal fixed fee in cents
            'fee_relative' => '0.034' // relative paypal fee
        ]
    ]
];