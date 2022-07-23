<?php
namespace PayPal\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PayPalPaymentsFixture extends TestFixture
{
    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'payment_id' => 'PayPalId',
            'remittance_identifier' => 'ri',
            'created' => '2022-07-23 18:32:00',
            'modified' => '2022-07-23 18:32:00',
        ],
        [
            'id' => 2,
            'remittance_identifier' => 'ab',
            'created' => '2022-07-23 18:33:00',
            'modified' => '2022-07-23 18:33:00',
        ]
    ];
}
