<?php

declare(strict_types=1);

use Pantono\Database\Migration\Base\BasePantonoMigration;

final class SubscriptionsMigration extends BasePantonoMigration
{
    public function change(): void
    {
        $this->tablePrefix('subscription_type')
            ->addColumn('name', 'string')
            ->addColumn('description', 'string')
            ->addColumn('allow_customer', 'boolean')
            ->addColumn('allow_company', 'boolean')
            ->addColumn('price_in_pence', 'integer')
            ->addColumn('active', 'boolean')
            ->addColumn('billing_interval_unit', 'string')
            ->addColumn('billing_interval_count', 'integer')
            ->addColumn('metadata', 'json')
            ->create();

        $this->tablePrefix('subscription_type_item')
            ->addLinkedColumn('subscription_type_id', $this->addTablePrefix('subscription_type'), 'id')
            ->addColumn('name', 'string')
            ->addColumn('quantity', 'integer')
            ->create();

        $this->tablePrefix('subscription_status')
            ->addColumn('name', 'string')
            ->addColumn('active', 'boolean')
            ->create();

        $this->insertOnCreate($this->addTablePrefix('subscription_status'), [
            ['id' => 1, 'name' => 'Active', 'active' => true], //Active sub
            ['id' => 2, 'name' => 'Payment Failed', 'active' => true], //Payment failed, but still active
            ['id' => 3, 'name' => 'Cancelled', 'active' => false], //Cancelled by user
            ['id' => 4, 'name' => 'Expired', 'active' => false], //Expired after failed payment
        ]);

        $this->tablePrefix('subscription')
            ->addColumn('date_created', 'datetime')
            ->addColumn('date_updated', 'datetime')
            ->addLinkedColumn('status_id', $this->addTablePrefix('subscription_status'), 'id')
            ->addLinkedColumn('type_id', $this->addTablePrefix('subscription_type'), 'id')
            ->addLinkedColumn('customer_id', $this->addTablePrefix('customer'), 'id', ['null' => true])
            ->addLinkedColumn('company_id', $this->addTablePrefix('company'), 'id', ['null' => true])
            ->addColumn('start_date', 'datetime')
            ->addColumn('end_date', 'datetime', ['null' => true])
            ->addColumn('price_in_pence', 'integer')
            ->addColumn('next_billing_date', 'datetime')
            ->addLinkedColumn('payment_method_id', $this->addTablePrefix('payment_mandate'), 'id', ['null' => true])
            ->addIndex('next_billing_date')
            ->create();

        $this->tablePrefix('subscription_period')
            ->addLinkedColumn('subscription_id', $this->addTablePrefix('subscription'), 'id')
            ->addColumn('start_date', 'datetime')
            ->addColumn('end_date', 'datetime')
            ->addColumn('price_in_pence', 'integer')
            ->addColumn('date_last_attempted', 'datetime')
            ->create();

        $this->tablePrefix('subscription_period_payment', ['id' => false, 'primary_key' => ['period_id', 'payment_id']])
            ->addLinkedColumn('period_id', $this->addTablePrefix('subscription_period'), 'id')
            ->addLinkedColumn('payment_id', $this->addTablePrefix('payment'), 'id')
            ->create();

        $this->tablePrefix('subscription_period_item')
            ->addLinkedColumn('period_id', $this->addTablePrefix('subscription_period'), 'id')
            ->addLinkedColumn('type_id', $this->addTablePrefix('subscription_type_item'), 'id')
            ->addColumn('date', 'datetime')
            ->addColumn('quantity', 'integer')
            ->addColumn('metadata', 'json')
            ->create();
    }
}
