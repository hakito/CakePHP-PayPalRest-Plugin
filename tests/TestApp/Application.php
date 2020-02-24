<?php

namespace PayPal\Test\TestApp;

use Cake\Http\BaseApplication;
use Cake\Routing\Middleware\RoutingMiddleware;

class Application extends BaseApplication
{

    /**
     * {@inheritDoc}
     */
    public function bootstrap()
    {
        // Call parent to load bootstrap from files.
        parent::bootstrap();

        $this->addPlugin(\PayPal\Plugin::class);
    }

    /**
     * Setup the middleware queue your application will use.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to setup.
     * @return \Cake\Http\MiddlewareQueue The updated middleware queue.
     */
    public function middleware($middlewareQueue)
    {
        $middlewareQueue
            ->add(new RoutingMiddleware($this));

        return $middlewareQueue;
    }
}
