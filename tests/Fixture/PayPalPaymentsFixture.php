<?php
namespace PayPal\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

class PayPalPaymentsFixture extends TestFixture
{

    /**
     * Table name
     *
     * @var string
     */
    public $table = 'PayPalPayments';

    /**
     * Fields
     *
     * @var array
     */
    public $fields = [
        'id' => ['type' => 'integer', 'length' => 10, 'unsigned' => true, 'null' => false, 'default' => null, 'comment' => '', 'autoIncrement' => true, 'precision' => null],
        // 'created' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        // 'modified' => ['type' => 'datetime', 'length' => null, 'null' => false, 'default' => null, 'comment' => '', 'precision' => null],
        'payment_id' => ['type' => 'string', 'length' => 50, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'payment_state' => ['type' => 'string', 'length' => 50, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        // 'sale_state' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        'remittance_identifier' => ['type' => 'text', 'length' => 100, 'unsigned' => false, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        // 'remitted_moment' => ['type' => 'integer', 'length' => 11, 'unsigned' => false, 'null' => true, 'default' => null, 'comment' => '', 'precision' => null, 'autoIncrement' => null],
        '_indexes' => [
            // //'parent_id' => ['type' => 'index', 'columns' => ['parent_id'], 'length' => []],
            // 'lft' => ['type' => 'index', 'columns' => ['lft'], 'length' => []],
            // 'rght' => ['type' => 'index', 'columns' => ['rght'], 'length' => []],
            // //'slug' => ['type' => 'index', 'columns' => ['slug'], 'length' => []],
            // 'is_visible' => ['type' => 'index', 'columns' => ['is_visible'], 'length' => []],
            // //'title' => ['type' => 'fulltext', 'columns' => ['title'], 'length' => []],
        ],
        '_constraints' => [
            'primary' => ['type' => 'primary', 'columns' => ['id'], 'length' => []],
        ],
        '_options' => [
            'engine' => 'InnoDB',
            //'collation' => 'utf8_general_ci'
        ],
    ];

    /**
     * Records
     *
     * @var array
     */
    public $records = [
        [
            'id' => 1,
            'payment_id' => 'PayPalId',
            'remittance_identifier' => 'ri'
        ],
        [
            'id' => 2
        ]
    ];
}
