<?php

namespace Pantono\Subscriptions\Tests;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pantono\Payments\Model\Payment;
use Pantono\Subscriptions\Filter\SubscriptionPeriodFilter;
use Pantono\Subscriptions\Model\Subscription;
use Pantono\Subscriptions\Model\SubscriptionPeriod;
use Pantono\Subscriptions\Model\SubscriptionStatus;
use Pantono\Subscriptions\Repository\SubscriptionsRepository;
use Phinx\Db\Adapter\SQLiteAdapter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\NullOutput;

require_once __DIR__ . '/TestDatabase.php';

class SubscriptionsRepositoryTest extends TestCase
{
    public function testPeriodSaveAndAtomicClaim(): void
    {
        $db = TestDatabase::create();
        $repository = new SubscriptionsRepository($db);
        $period = $this->period();
        $repository->saveSubscriptionPeriod($period);
        self::assertNotNull($period->getId());
        self::assertNull($repository->getRenewalPeriod(1, $period->getStartDate())['date_last_attempted']);
        $now = new \DateTimeImmutable('2026-02-01 10:00:00');
        self::assertTrue($repository->claimPeriodPayment($period->getId(), $now));
        self::assertFalse($repository->claimPeriodPayment($period->getId(), $now));
        $repository->saveSubscriptionPeriod($period);
        self::assertSame('2026-02-01 10:00:00', $repository->getRenewalPeriod(1, $period->getStartDate())['date_last_attempted']);
        self::assertFalse($repository->claimPeriodPayment($period->getId(), $now));
    }

    public function testPeriodAndLinksRollBackTogether(): void
    {
        $db = TestDatabase::create();
        $repository = new SubscriptionsRepository($db);
        $period = $this->period();
        $repository->saveSubscriptionPeriod($period);
        $db->getDoctrineConnection()->insert('payment', ['id' => 1]);
        $payment = new Payment();
        $payment->setId(1);
        $period->setPayments([$payment, $payment]);
        $repository->saveSubscriptionPeriod($period);
        self::assertSame([1], $repository->getPeriodPaymentIds($period->getId()));
        $period->setPriceInPence(999);
        $period->setPayments([new Payment()]);
        try {
            $repository->saveSubscriptionPeriod($period);
            self::fail('Unsaved payments must be rejected');
        } catch (\InvalidArgumentException $e) {
            self::assertSame([1], $repository->getPeriodPaymentIds($period->getId()));
            self::assertSame(100, (int)$repository->getRenewalPeriod(1, $period->getStartDate())['price_in_pence']);
        }
    }

    public function testClaimChecksCurrentSubscriptionStatusAndSchedule(): void
    {
        $db = TestDatabase::create();
        $repository = new SubscriptionsRepository($db);
        $period = $this->period();
        $repository->saveSubscriptionPeriod($period);
        $now = new \DateTimeImmutable('2026-02-01');
        self::assertFalse($repository->claimPeriodPayment($period->getId(), new \DateTimeImmutable('2026-01-30')));
        $connection = $db->getDoctrineConnection();
        $connection->update('subscription', ['status_id' => 3], ['id' => 1]);
        self::assertFalse($repository->claimPeriodPayment($period->getId(), $now));
        $connection->update('subscription', ['status_id' => 1, 'next_billing_date' => '2026-02-28 12:00:00'], ['id' => 1]);
        self::assertFalse($repository->claimPeriodPayment($period->getId(), $now));
        $connection->update('subscription', ['next_billing_date' => '2026-01-31 12:00:00', 'end_date' => '2026-02-01 00:00:00'], ['id' => 1]);
        self::assertFalse($repository->claimPeriodPayment($period->getId(), $now));
        self::assertNull($repository->getRenewalPeriod(1, $period->getStartDate())['date_last_attempted']);
    }

    public function testDuplicateRenewalIsRejected(): void
    {
        $repository = new SubscriptionsRepository(TestDatabase::create());
        $repository->saveSubscriptionPeriod($this->period());
        $this->expectException(UniqueConstraintViolationException::class);
        $repository->saveSubscriptionPeriod($this->period());
    }

    public function testPeriodFilterIsScopedAndEndExclusive(): void
    {
        $repository = new SubscriptionsRepository(TestDatabase::create());
        $period = $this->period();
        $repository->saveSubscriptionPeriod($period);
        $filter = new SubscriptionPeriodFilter();
        $filter->setSubscription($period->getSubscription());
        $filter->setDateWithin($period->getStartDate());
        self::assertCount(1, $repository->getSubscriptionPeriodsByFilter($filter));
        $filter->setDateWithin($period->getEndDate());
        self::assertSame([], $repository->getSubscriptionPeriodsByFilter($filter));
        $other = new Subscription();
        $other->setId(2);
        $filter->setSubscription($other);
        $filter->setDateWithin($period->getStartDate());
        self::assertSame([], $repository->getSubscriptionPeriodsByFilter($filter));
    }

    public function testKeysetBatchDoesNotSkipRowsWhenDueSetShrinks(): void
    {
        $db = TestDatabase::create();
        $repository = new SubscriptionsRepository($db);
        $connection = $db->getDoctrineConnection();
        $base = $connection->fetchAssociative('SELECT * FROM subscription WHERE id = 1');
        for ($id = 2; $id <= 205; $id++) {
            $base['id'] = $id;
            $connection->insert('subscription', $base);
        }
        $base['id'] = 206;
        $base['next_billing_date'] = '2026-02-01 23:00:00';
        $connection->insert('subscription', $base);
        $connection->update('subscription', ['status_id' => 2], ['id' => 205]);
        $afterId = 0;
        $seen = [];
        while ($rows = $repository->getDueSubscriptions(new \DateTimeImmutable('2026-02-01 12:00:00'), $afterId)) {
            self::assertLessThanOrEqual(100, count($rows));
            foreach ($rows as $row) {
                $afterId = (int)$row['id'];
                $seen[] = $afterId;
                $connection->update('subscription', ['next_billing_date' => '2026-03-01 12:00:00'], ['id' => $afterId]);
            }
        }
        self::assertSame(range(1, 205), $seen);
    }

    public function testRenewalUpdateDoesNotOverwriteCancellationOrAdvanceTwice(): void
    {
        $db = TestDatabase::create();
        $repository = new SubscriptionsRepository($db);
        $subscription = $this->period()->getSubscription();
        $status = new SubscriptionStatus();
        $status->setId(1);
        $subscription->setStatus($status);
        $subscription->setDateUpdated(new \DateTimeImmutable('2026-02-01'));
        $subscription->setNextBillingDate(new \DateTimeImmutable('2026-02-28 12:00:00'));
        $expected = new \DateTimeImmutable('2026-01-31 12:00:00');
        self::assertTrue($repository->saveRenewalSubscription($subscription, $expected));
        self::assertFalse($repository->saveRenewalSubscription($subscription, $expected));
        $db->getDoctrineConnection()->update('subscription', ['status_id' => 3], ['id' => 1]);
        self::assertFalse($repository->saveRenewalSubscription($subscription, $subscription->getNextBillingDate()));
    }

    public function testClaimIsRefusedInsideAnEnclosingTransaction(): void
    {
        $db = TestDatabase::create();
        $repository = new SubscriptionsRepository($db);
        $period = $this->period();
        $repository->saveSubscriptionPeriod($period);
        $db->beginTransaction();
        try {
            $this->expectException(\LogicException::class);
            $repository->claimPeriodPayment($period->getId(), new \DateTimeImmutable());
        } finally {
            $db->rollback();
        }
    }

    public function testMigrationProtectsLegacyPeriodsAndRollsBack(): void
    {
        $db = TestDatabase::create(false);
        $connection = $db->getDoctrineConnection();
        $connection->executeStatement('ALTER TABLE subscription_period DROP COLUMN date_last_attempted');
        $connection->insert('subscription_period', ['subscription_id' => 1, 'start_date' => '2026-01-31 12:00:00', 'end_date' => '2026-02-28 12:00:00', 'price_in_pence' => 100]);
        $migration = new \SubscriptionRenewalSafety('test', 20261013150000);
        $adapter = new SQLiteAdapter(['adapter' => 'sqlite', 'connection' => $connection->getNativeConnection()], null, new NullOutput());
        $migration->setAdapter($adapter);
        $migration->up();
        self::assertSame('2026-01-31 12:00:00', $connection->fetchOne('SELECT date_last_attempted FROM subscription_period'));
        self::assertTrue($adapter->hasIndex('subscription_period', ['subscription_id', 'start_date']));
        $migration->down();
        self::assertFalse($adapter->hasColumn('subscription_period', 'payment_error'));
    }

    public function testMigrationRejectsLegacyDuplicatesWithoutDeletingThem(): void
    {
        $db = TestDatabase::create(false);
        $connection = $db->getDoctrineConnection();
        for ($id = 1; $id <= 2; $id++) {
            $connection->insert('subscription_period', ['subscription_id' => 1, 'start_date' => '2026-01-31 12:00:00', 'end_date' => '2026-02-28 12:00:00', 'price_in_pence' => 100, 'date_last_attempted' => '2026-01-31 12:00:00']);
        }
        $migration = new \SubscriptionRenewalSafety('test', 20261013150000);
        $migration->setAdapter(new SQLiteAdapter(['adapter' => 'sqlite', 'connection' => $connection->getNativeConnection()], null, new NullOutput()));
        try {
            $migration->up();
            self::fail('Duplicate periods need manual reconciliation');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Reconcile duplicate', $e->getMessage());
            self::assertSame(2, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period'));
        }
    }

    private function period(): SubscriptionPeriod
    {
        $subscription = new Subscription();
        $subscription->setId(1);
        $period = new SubscriptionPeriod();
        $period->setSubscription($subscription);
        $period->setStartDate(new \DateTimeImmutable('2026-01-31 12:00:00'));
        $period->setEndDate(new \DateTimeImmutable('2026-02-28 12:00:00'));
        $period->setPriceInPence(100);
        return $period;
    }
}