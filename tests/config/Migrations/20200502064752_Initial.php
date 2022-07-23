<?php
use Migrations\AbstractMigration;

class Initial extends AbstractMigration
{

    public $autoId = false;

    public function up()
    {
        $this->table('PayPalPayments')
            ->addColumn('id', 'integer', [
                'autoIncrement' => true,
                'default' => null,
                'limit' => 10,
                'null' => false,
                'signed' => false,
            ])
            ->addPrimaryKey(['id'])
            ->addColumn('created', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('updated', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => false,
            ])
            ->addColumn('payment_id', 'string', [
                'default' => null,
                'limit' => 50,
                'null' => true,
            ])
            ->addColumn('payment_state', 'string', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('sale_state', 'string', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addColumn('remittance_identifier', 'string', [
                'default' => null,
                'limit' => 100,
                'null' => false,
            ])
            ->addColumn('remitted_moment', 'datetime', [
                'default' => null,
                'limit' => null,
                'null' => true,
            ])
            ->addIndex(
                [
                    'payment_id',
                ],
                ['unique' => true]
            )
            ->addIndex(
                [
                    'sale_state',
                    'remitted_moment',
                ]
            )
            ->addIndex(
                [
                    'payment_state',
                ]
            )
            ->addIndex(
                [
                    'payment_state',
                    'sale_state',
                ]
            )
            ->create();
    }

    public function down()
    {
        $this->table('PayPalPayments')->drop()->save();
    }
}
