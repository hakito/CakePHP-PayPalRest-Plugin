<?php
namespace PayPal\Test\TestCase\Controller;

use Cake\TestSuite\TestCase;
use Cake\TestSuite\IntegrationTestTrait;
use PayPal\Test\TestApp\Application;

use PayPal\PaymentController;

class PaymentNotificationsControllerTest extends TestCase
{
    use IntegrationTestTrait;

    public $fixtures = ['plugin.PayPal.PayPalPayments'];

    public function setUp()
    {
        $this->disableErrorHandlerMiddleware();
        $this->model = $this->getMockBuilder(\PayPal\Model\Table\PayPalPaymentsTable::class)
            ->disableOriginalConstructor()
            ->setMethods(['refreshState', 'decryptRedirectUrl', 'execute', 'setNextCheck'])
            ->getMock();
        $this->mockModel = true;
    }

    public function controllerSpy($event, $controller = null)
    {
        /* @var $controller PayPalController */
        $this->controller = $event->getSubject();
        if ($this->mockModel)
            $this->controller->PayPalPayments = $this->model;
    }

    public function testModelLoaded()
    {
        $this->mockModel = false;
        $this->get('/PayPalPayment/Lookup/815');
        $this->assertInstanceOf(\PayPal\Model\Table\PayPalPaymentsTable::class, $this->controller->PayPalPayments);
        $this->assertNotEquals($this->model, $this->controller->PayPalPayments);
    }

    public function testLookupCallsRefreshState()
    {
        $this->model->expects($this->once())
            ->method('refreshState')
            ->with('815');
        $this->get('/PayPalPayment/Lookup/815');
    }

    public function testExecuteDecryptsRedirectUrl()
    {
        $this->model->expects($this->once())
            ->method('decryptRedirectUrl')
            ->with('redirectUrl')
            ->willReturn(false);
        $this->expectException(\Cake\Http\Exception\NotFoundException::class);
        $this->get('/PayPalPayment/Execute/815/1/redirectUrl');
    }

    public function testExecuteExecutesOnSuccess()
    {
        $this->model->expects($this->once())
            ->method('decryptRedirectUrl')
            ->with('redirectUrl')
            ->willReturn('http://example.com');

        $this->model->expects($this->once())
            ->method('execute')
            ->with('815');

        $this->get('/PayPalPayment/Execute/815/1/redirectUrl');
    }

    public function testRedirect()
    {
        $this->model->expects($this->once())
            ->method('decryptRedirectUrl')
            ->willReturn('http://example.com');

        $this->get('/PayPalPayment/Execute/815/1/dummy');
        $this->assertRedirect('http://example.com');
    }

    public function testRedirectAppendsParams()
    {
        $this->model->expects($this->once())
            ->method('decryptRedirectUrl')
            ->willReturn('http://example.com');

        $this->get('/PayPalPayment/Execute/815/1/dummy?foo=bar');
        $this->assertRedirect('http://example.com?foo=bar');
    }

    public function testRedirectInjectsParams()
    {
        $this->model->expects($this->once())
            ->method('decryptRedirectUrl')
            ->willReturn('http://example.com?a=b');

        $this->get('/PayPalPayment/Execute/815/1/dummy?foo=bar');
        $this->assertRedirect('http://example.com?a=b&foo=bar');
    }
}