<?php

namespace PayPal\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\TestSuite\TestCase;
use PayPal\Controller\Component\PayPalComponent;
use PayPal\Model\Table\PayPalPaymentsTable;

/**
 * @property PayPalComponent $PayPal
 */
class PayPalComponentTest extends TestCase
{

    public function setUp(): void
    {
        parent::setUp();

        /** @var Controller */
        $this->Controller = $this->getMockBuilder('\Cake\Controller\Controller')
            ->onlyMethods(['redirect'])
            ->getMock();

        $registry = new ComponentRegistry($this->Controller);
        $this->PayPal = new PayPalComponent($registry);

        $event = new Event('Controller.startup', $this->Controller);
        $this->PayPal->startup($event);

        $this->items = new \ReflectionProperty(PayPalComponent::class, 'items');
        $this->items->setAccessible(true);
    }

    public function testInitialized()
    {
        $this->assertEquals(Configure::read('PayPal'), $this->PayPal->config);
        $this->assertInstanceOf(PayPalPaymentsTable::class, $this->PayPal->PayPalPayments);
    }

    public function testAddArticle()
    {
        $this->PayPal->AddArticle('foo', 3, 1234);
        $items = $this->items->getValue($this->PayPal);
        $this->assertEquals(1, count($items));
        $this->assertEquals('foo', $items[0]->getName());
        $this->assertEquals(3, $items[0]->getQuantity());
        $this->assertEquals(12.34, $items[0]->getPrice());
        $this->assertEquals('EUR', $items[0]->getCurrency());
        $this->assertEquals(null, $items[0]->getSku());
    }

    public function testAddArticleWithId()
    {
        $this->PayPal->AddArticle('foo', 3, 1234, 'id');
        $items = $this->items->getValue($this->PayPal);
        $this->assertEquals(1, count($items));
        $this->assertEquals('foo', $items['id']->getName());
        $this->assertEquals(3, $items['id']->getQuantity());
        $this->assertEquals(12.34, $items['id']->getPrice());
        $this->assertEquals('EUR', $items['id']->getCurrency());
        $this->assertEquals('id', $items['id']->getSku());
    }

    public function testGetItemTotal()
    {
        $this->PayPal->AddArticle('foo', 3, 1234);
        $this->PayPal->AddArticle('foo', 5, 567);
        $this->assertEquals(1234 * 3 + 567 * 5, $this->PayPal->GetItemTotal());
    }

    public function testPaymentRedirectCreatePaymentFails()
    {
        $this->PayPal->PayPalPayments = $this->getMockForModel('PayPal.PayPalPayments', ['createPayment']);
        $this->PayPal->PayPalPayments->expects($this->once())
            ->method('createPayment')
            ->with('ri', $this->anything(), 'https://ok', 'https://cancel')
            ->will($this->throwException(new \Exception('dummy')));
        $this->expectException(\Exception::class, 'dummy');
        $this->PayPal->PaymentRedirect('ri', 'https://ok', 'https://cancel', 'descr');
    }

    public function testPaymentRedirect()
    {
        $this->PayPal->AddArticle('foo', 2, 1234, 'bar');
        $this->PayPal->Shipping = 100;

        $this->PayPal->PayPalPayments = $this->getMockForModel('PayPal.PayPalPayments', ['createPayment']);

        $this->PayPal->PayPalPayments->expects($this->once())
            ->method('createPayment')
            ->with('ri', $this->callback(function($payment) {
                $this->assertEquals(29.62 + 1, $payment->getTransactions()[0]->getAmount()->getTotal());
                return true;
            }), 'https://ok', 'https://cancel')
            ->will($this->returnCallback(function($remittanceIdentifier, $payment, $okUrl, $cancelUrl){
                $links = new \PayPal\Api\Links();
                $links
                    ->setRel('approval_url')
                    ->setHref('https://pay');
                $payment->setLinks([$links]);
                return true;
            }));

        $this->Controller->expects($this->once())
            ->method('redirect')
            ->with('https://pay');

        $this->PayPal->PaymentRedirect('ri', 'https://ok', 'https://cancel', 'descr');
    }

    public function testPaymentRedirectWithTax()
    {
        $this->PayPal->AddArticle('foo', 2, 1234, 'bar');
        $this->PayPal->Shipping = 100;

        $this->PayPal->PayPalPayments = $this->getMockForModel('PayPal.PayPalPayments', ['createPayment']);

        $this->PayPal->PayPalPayments->expects($this->once())
            ->method('createPayment')
            ->with('ri', $this->callback(function($payment) {
                $this->assertEquals(12.34 * 2 + 3.21 + 1, $payment->getTransactions()[0]->getAmount()->getTotal());
                return true;
            }), 'https://ok', 'https://cancel')
            ->will($this->returnCallback(function($remittanceIdentifier, $payment, $okUrl, $cancelUrl){
                $links = new \PayPal\Api\Links();
                $links
                    ->setRel('approval_url')
                    ->setHref('https://pay');
                $payment->setLinks([$links]);
                return true;
            }));

        $this->Controller->expects($this->once())
            ->method('redirect')
            ->with('https://pay');

        $this->PayPal->PaymentRedirect('ri', 'https://ok', 'https://cancel', 'descr', 321);
    }

    public function testNeutralizeFee()
    {
        $actual = PayPalComponent::NeutralizeFee(1234);
        $this->assertEquals(1314, $actual);
    }
}