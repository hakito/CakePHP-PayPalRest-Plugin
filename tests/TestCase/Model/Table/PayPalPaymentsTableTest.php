<?php

namespace PayPal\Test\TestCase\Model\Table;

use Cake\Event\EventList;
use Cake\Event\EventManager;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use PayPal\Api\Payment;
use PayPal\Api\RelatedResources;
use PayPal\Api\Sale;
use PayPal\Model\Entity\PayPalPayment;
use PayPal\Model\Table\PayPalPaymentsTable;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @var \PayPal\Model\Table\PayPalPaymentTable PayPalPayment
 */
class PayPalPaymentsTableTest extends TestCase
{
    public $fixtures = ['plugin.PayPal.PayPalPayments'];

    public function setUp(): void
    {
        parent::setUp();
        $this->PayPalPayments = $this->getTableLocator()->get('PayPal.PayPalPayments');
        /** @var EventManager */
        $eventManager = $this->PayPalPayments->getEventManager();
        $eventManager->setEventList(new EventList());
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
        /** @var MockObject|PayPalPaymentsTable */
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['ApiGet', 'savePayment']);

        $dummy = new Payment();
        $model->expects($this->once())
            ->method('ApiGet')
            ->with('PayPalId')
            ->willReturn($dummy);

        $model->expects($this->once())
            ->method('savePayment')
            ->with($dummy);

        $model->refreshState('1');
    }

    public function testSavePayment()
    {
        /** @var PayPalPaymentsTable */
        $model = $this->getMockForModel('PayPal.PayPalPayments', ['getRelatedResources']);

        $transactions = [new \PayPal\Api\Transaction()];
        $model->expects($this->once())
            ->method('getRelatedResources')
            ->with($transactions);

        $p = new Payment();
        $p->setId('A_new_id');
        $p->setState('initial');
        $p->setTransactions($transactions);

        $result = $model->savePayment($p, null, 'myri');
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

        $result = $model->savePayment($p, null, 'myri', true);
        $this->assertEquals('done', $result->sale_state);
        $this->assertNotEmpty($result->remitted_moment);
    }

    public function testCreatePayment()
    {
        /** @var MockObject|Payment */
        $p = $this->getMockBuilder(Payment::class)
            ->onlyMethods(['create'])
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

        $entity = new PayPalPayment();
        $model->expects($this->once())
            ->method('savePayment')
            ->with($p, $this->greaterThan(0))
            ->willReturn($entity);

        $ret = $model->createPayment('ri', $p, 'http://ok', 'http://cancel');
        $this->assertEquals($entity, $ret);
        $redirectUrls = $p->getRedirectUrls();
        $this->assertTextContains('1/ok', $redirectUrls->getReturnUrl());
        $this->assertTextContains('0/cancel', $redirectUrls->getCancelUrl());
    }

    private function prepareExecuteTest($saleState = 'completed', $paymentState = 'approved')
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments',
        [
            'ApiGet',
            'savePayment',
            'getRelatedResources',
        ]);

        /** @var EventManager */
        $eventManager = $model->getEventManager();
        $eventManager->setEventList(new EventList());

        /** @var MockObject|Payment */
        $p = $this->getMockBuilder(Payment::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $p->setState($paymentState);

        $rr = new RelatedResources();
        $s = new Sale();
        $rr->setSale($s);
        $s->setState($saleState);

        $model->expects($this->once())
            ->method('ApiGet')
            ->with('PayPalId')
            ->willReturn($p);

        $model->expects($this->once())
            ->method('getRelatedResources')
            ->willReturn($rr);

        $model->getEventManager()->on('PayPal.BeforePaymentExecution',
        function($event, $remittanceIdentifier)
        {
            return ['handled' => true];
        });

        $p->expects($this->once())
            ->method('execute')
            ->willReturn($p);

        return $model;
    }

    public function testExecuteCompletedAndApproved()
    {
        $model = $this->prepareExecuteTest();

        $model->expects($this->once())
            ->method('savePayment')
            ->with($this->anything(), null, 'ri', true);

        $model->execute(1, 'payer');

        $this->assertEventFiredWith('PayPal.BeforePaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
        $this->assertEventFiredWith('PayPal.AfterPaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
    }

    public function testExecuteCancelOnInvalidSaleState()
    {
        $model = $this->prepareExecuteTest('invalid');

        $model->execute(1, 'payer');
        $this->assertEventFiredWith('PayPal.BeforePaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
        $this->assertEventFiredWith('PayPal.CancelPaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
    }

    public function testExecuteCancelOnInvalidPaymentState()
    {
        $model = $this->prepareExecuteTest('completed', 'invalid');

        $model->execute(1, 'payer');
        $this->assertEventFiredWith('PayPal.BeforePaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
        $this->assertEventFiredWith('PayPal.CancelPaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
    }

    public function testExecuteCancelOnException()
    {
        $model = $this->getMockForModel('PayPal.PayPalPayments',
        [
            'ApiGet'
        ]);

        /** @var EventManager */
        $eventManager = $model->getEventManager();
        $eventManager->setEventList(new EventList());

        /** @var MockObject|Payment */
        $p = $this->getMockBuilder(Payment::class)
            ->onlyMethods(['execute'])
            ->getMock();

        $p->expects($this->once())
            ->method('execute')
            ->will($this->throwException(new \Exception('dummy')));

        $model->expects($this->once())
            ->method('ApiGet')
            ->with('PayPalId')
            ->willReturn($p);
        $model->getEventManager()->on('PayPal.BeforePaymentExecution',
        function($event, $remittanceIdentifier)
        {
            return ['handled' => true];
        });

        try {
            $model->execute(1, 'payer');
        } catch (\Exception $th) {
            $this->assertEquals('dummy', $th->getMessage());
        }
        $this->assertEventFiredWith('PayPal.BeforePaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
        $this->assertEventFiredWith('PayPal.CancelPaymentExecution', 'RemittanceIdentifier', 'ri', $model->getEventManager());
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
        $actual = $method->invokeArgs($this->PayPalPayments, [$transactions]);

        $this->assertEquals($rr, $actual);
        $transaction->setRelatedResources([$rr]);
        $actual = $method->invokeArgs($this->PayPalPayments, [$transactions]);
        $this->assertEquals($rr, $actual);

        $transaction->setRelatedResources([]);
        $actual = $method->invokeArgs($this->PayPalPayments, [$transactions]);
        $this->assertNull($actual);
    }

    public function testEncryptionRoundTrip()
    {
        // encrypt with sandbox credentials
        $encrypted = $this->PayPalPayments->encryptRedirectUrl('foo', 123);
        $actual = $this->PayPalPayments->decryptRedirectUrl($encrypted, 123);
        $this->assertEquals('foo', $actual);
    }

    public function testBase64UrlEncode()
    {
        $plus = chr(0x3e << 2);
        $slash = chr(0x3f);
        $value = "$plus\x0$slash\o\x3Fo";
        $actual = PayPalPaymentsTable::base64_url_encode($value);
        $this->assertEquals('-AA_XG8_bw,,', $actual);
    }

    public function testBase64UrlDecode()
    {
        $plus = chr(0x3e << 2);
        $slash = chr(0x3f);
        $value = "$plus\x0$slash\o\x3Fo";
        $actual = PayPalPaymentsTable::base64_url_decode('-AA_XG8_bw,,');
        $this->assertEquals($value, $actual);
    }
}
