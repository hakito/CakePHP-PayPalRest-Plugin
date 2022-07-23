<?php
use Migrations\AbstractMigration;

class Sanitize extends AbstractMigration
{
    public function change()
    {
        $this->table('PayPalPayments')
            ->rename('pay_pal_payments')
            ->save();
        $this->table('pay_pal_payments')
            ->renameColumn('updated', 'modified')
            ->update();
    }
}
