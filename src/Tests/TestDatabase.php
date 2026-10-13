<?php

namespace Pantono\Subscriptions\Tests;

use Pantono\Database\Adapter\Db;
use Phinx\Db\Adapter\SQLiteAdapter;
use Symfony\Component\Console\Output\NullOutput;

class TestDatabase
{
    public static function create(bool $migrate = true): Db
    {
        $db = new class('pdo-sqlite:///:memory:') extends Db {
            public function foreignKeyChecks(bool $enabled): void
            {
                $this->getDoctrineConnection()->executeStatement('PRAGMA foreign_keys = ' . ($enabled ? 'ON' : 'OFF'));
            }
        };
        $connection = $db->getDoctrineConnection();
        foreach (['customer', 'company', 'payment_mandate', 'payment'] as $table) {
            $connection->executeStatement('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY)');
        }
        require_once __DIR__ . '/../../migrations/20170300100001_subscriptions_migration.php';
        require_once __DIR__ . '/../../migrations/20261013150000_subscription_renewal_safety.php';
        $adapter = new SQLiteAdapter(['adapter' => 'sqlite', 'connection' => $connection->getNativeConnection()], null, new NullOutput());
        $initial = new \SubscriptionsMigration('test', 20170300100001);
        $initial->setAdapter($adapter);
        $initial->change();
        if ($migrate) {
            $migration = new \SubscriptionRenewalSafety('test', 20261013150000);
            $migration->setAdapter($adapter);
            $migration->up();
        }
        $db->foreignKeyChecks(true);
        $connection->insert('subscription_type', [
            'id' => 1, 'name' => 'Plan', 'description' => '', 'allow_customer' => 1, 'allow_company' => 1,
            'price_in_pence' => 100, 'active' => 1, 'billing_interval_unit' => 'month', 'billing_interval_count' => 1, 'metadata' => '{}'
        ]);
        $connection->insert('subscription', [
            'id' => 1, 'date_created' => '2026-01-31 12:00:00', 'date_updated' => '2026-01-31 12:00:00',
            'status_id' => 1, 'type_id' => 1, 'start_date' => '2026-01-31 12:00:00',
            'price_in_pence' => 100, 'next_billing_date' => '2026-01-31 12:00:00'
        ]);
        return $db;
    }
}