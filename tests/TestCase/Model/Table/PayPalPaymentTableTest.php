<?php

namespace PayPal\Test\TestCase\Model\Table;

use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

use PayPal\Api\Payment;
use PayPal\Api\RelatedResources;
use PayPal\Api\Sale;
use PayPal\Model\Table\PayPalPaymentsTable;

/**
 * @var \PayPal\Model\Table\PayPalPaymentTable PayPalPayment
 */
class PayPalPaymentTableTest extends TestCase
{
    public $fixtures = ['plugin.PayPal.PayPalPayments'];

    public function setUp()
    {
        parent::setUp();
        $this->PayPalPayments = TableRegistry::getTableLocator()->get('PayPal.PayPalPayments');
    }

    public function testGetApiContext()
    {
        $apiContext = PayPalPaymentsTable::getApiContext();
        $this->assertInstanceof(\PayPal\Rest\ApiContext::class, $apiContext);
    }

    public function testRefreshStateForUnknownId()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['save']);
        $model->expects($this->never())
            ->method('save');

        $ret = $model->refreshState('815');
        $this->assertFalse($ret);
    }

    public function testRefreshState()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['ApiGet', 'savePayment']);

        $model->expects($this->once())
            ->method('ApiGet')
            ->with('PayPalId')
            ->willReturn('dummy');

        $model->expects($this->once())
            ->method('savePayment')
            ->with('dummy');

        $model->refreshState('1');
    }

    public function testSavePayment()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['getRelatedResources']);

        $transactions = [new \PayPal\Api\Transaction()];
        $model->expects($this->once())
            ->method('getRelatedResources')
            ->with($transactions);

        $p = new Payment();
        $p->setId('A_new_id');
        $p->setState('initial');
        $p->setTransactions($transactions);

        $result = $model->savePayment($p);
        $this->assertEquals('initial', $result->payment_state);
        $this->assertEquals('A_new_id', $result->payment_id);
    }

    public function testSavePaymentByPaymentId()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['getRelatedResources']);

        $transactions = [new \PayPal\Api\Transaction()];
        $model->expects($this->once())
            ->method('getRelatedResources')
            ->with($transactions);

        $p = new Payment();
        $p->setId('PayPalId');
        $p->setState('completed');
        $p->setTransactions($transactions);

        $result = $model->savePayment($p);
        $this->assertEquals('completed', $result->payment_state);
        $this->assertEquals(1, $result->id);
    }

    public function testSavePaymentById()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['getRelatedResources']);

        $transactions = [new \PayPal\Api\Transaction()];
        $model->expects($this->once())
            ->method('getRelatedResources')
            ->with($transactions);

        $p = new Payment();
        $p->setId('foo');
        $p->setState('completed');
        $p->setTransactions($transactions);

        $result = $model->savePayment($p, 2);
        $this->assertEquals('completed', $result->payment_state);
        $this->assertEquals('foo', $result->payment_id);
        $this->assertEquals(2, $result->id);
    }

    public function testSaveSaleState()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['getRelatedResources']);

        $sale = new Sale();
        $sale->setState('done');

        $relatedResources = new RelatedResources();
        $relatedResources->setSale($sale);

        $transactions = [new \PayPal\Api\Transaction()];
        $model->expects($this->once())
            ->method('getRelatedResources')
            ->with($transactions)
            ->willReturn($relatedResources);

        $p = new Payment();
        $p->setId('foo');
        $p->setTransactions($transactions);

        $result = $model->savePayment($p);
        $this->assertEquals('done', $result->sale_state);
    }

    public function testCreatePayment()
    {
        $p = $this->getMockBuilder(Payment::class)
            ->setMethods(['create'])
            ->getMock();

        $p->expects($this->once())
            ->method('create')
            ->will($this->returnCallback(
                function() use ($p) { $p->setId('foo'); return $p; }));

        $model = $this->getMockForModel('PayPal.PayPalPayments', ['encryptRedirectUrl', 'savePayment']);
        $model->expects($this->exactly(2))
            ->method('encryptRedirecturl')
            ->withConsecutive(
                ['http://ok', $this->greaterThan(0)],
                ['http://cancel', $this->greaterThan(0)])
            ->willReturn('ok', 'cancel');

        $model->expects($this->once())
            ->method('savePayment')
            ->with($p, $this->greaterThan(0))
            ->willReturn('saved');

        $ret = $model->createPayment('ri', $p, 'http://ok', 'http://cancel');
        $this->assertEquals('saved', $ret);
        $redirectUrls = $p->getRedirectUrls();
        $this->assertTextContains('1/ok', $redirectUrls->getReturnUrl());
        $this->assertTextContains('0/cancel', $redirectUrls->getCancelUrl());
    }

    public function testExecuteCompletedAndApproved()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments',
        [
            'ApiGet',
            'savePayment',
            'getRelatedResources',
            'beforePayPalPaymentExecution',
            'afterPayPalPaymentExecution'
        ]);

        $p = $this->getMockBuilder(Payment::class)
            ->setMethods(['execute'])
            ->getMock();

        $p->setState('approved');

        $rr = new RelatedResources();
        $s = new Sale();
        $rr->setSale($s);
        $s->setState('completed');

        $model->expects($this->once())
            ->method('ApiGet')
            ->with('PayPalId')
            ->willReturn($p);

        $model->expects($this->once())
            ->method('getRelatedResources')
            ->willReturn($rr);

        $model->expects($this->once())
            ->method('beforePayPalPaymentExecution')
            ->with('ri')
            ->willReturn(true);

        $model->expects($this->once())
            ->method('afterPayPalPaymentExecution')
            ->with('ri');

        $p->expects($this->once())
            ->method('execute')
            ->willReturn($p);

        $model->execute(1);
    }

    public function testGetRelatedResources()
    {
        $class = new \ReflectionClass(PayPalPaymentsTable::class);
        $method = $class->getMethod('getRelatedResources');
        $method->setAccessible(true);

        $transaction = new \PayPal\Api\Transaction();
        $rr = new RelatedResources();
        $transaction->setRelatedResources($rr);
        $transactions = [$transaction];
        $actual = $method->invokeArgs(null, [$transactions]);

        $this->assertEquals($rr, $actual);
        $transaction->setRelatedResources([$rr]);
        $actual = $method->invokeArgs(null, [$transactions]);
        $this->assertEquals($rr, $actual);
    }
}
