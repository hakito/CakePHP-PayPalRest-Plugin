<?php

use Cake\Routing\RouteBuilder;
use Cake\Routing\Router;

Router::plugin(
    'PayPal',
    ['path' => '/PayPalPayment'],
    function (RouteBuilder $routes)
    {
        /* Add route for handling payment notifications */
        $routes->get('/Execute/:id/:success/:redirect',
            [
                'controller' => 'Payment',
                'action' => 'execute'
            ],
        )
            ->setPatterns(['id' => '[0-9]+', 'success' => '[01]'])
            ->setPass(['id', 'success', 'redirect']);


        $routes->get('/Lookup/:id',
            [
                'controller' => 'Payment',
                'action' => 'lookup'
            ],
        )
            ->setPatterns(['id' => '[0-9]+'])
            ->setPass(['id']);
    }
);