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