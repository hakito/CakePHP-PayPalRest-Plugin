<?php

/* Add route for handling payment notifications */
Router::connect('/PayPalPayment/Execute/:id/:redirect', array(
	'plugin' => 'PayPal',
	'controller' => 'PayPalPayments',
	'action' => 'execute',    
        ),
    array(
        'pass' => array('id', 'redirect'),
        'id' => '[0-9]+'
         )
);

Router::connect('/PayPalPayment/Lookup/:id', array(
	'plugin' => 'PayPal',
	'controller' => 'PayPalPayments',
	'action' => 'lookup',
        ),
    array(
        'pass' => array('id'),
        'id' => '[0-9]+'
         )
);