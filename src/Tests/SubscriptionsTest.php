<?php

namespace Pantono\Subscriptions\Tests;

use Pantono\Core\Events\EventHelper;
use Pantono\Hydrator\Hydrator;
use Pantono\Payments\Payments;
use Pantono\Payments\Model\Payment;
use Pantono\Payments\Model\PaymentStatus;
use Pantono\Payments\Model\PaymentMandate;
use Pantono\Payments\Model\PaymentGateway;
use Pantono\Payments\Provider\AbstractProvider;
use Pantono\Subscriptions\Model\SubscriptionStatus;
use Pantono\Subscriptions\Model\SubscriptionTypeItem;
use Pantono\Subscriptions\Model\SubscriptionPeriodItem;
use Pantono\Subscriptions\Event\PostSubscriptionPeriodItemSaveEvent;
use Pantono\Subscriptions\Event\PreSubscriptionSaveEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Doctrine\DBAL\Connection;
use Pantono\Subscriptions\Model\Subscription;
use Pantono\Subscriptions\Model\SubscriptionPeriod;
use Pantono\Subscriptions\Model\SubscriptionType;
use Pantono\Subscriptions\Repository\SubscriptionsRepository;
use Pantono\Subscriptions\Subscriptions;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/TestDatabase.php';

class SubscriptionsTest extends TestCase
{
    public function testPaymentDescriptor(): void
    {
        $period = new SubscriptionPeriod();
        $period->setSubscription($this->subscription());
        $period->setStartDate(new \DateTimeImmutable('2026-01-31'));
        $period->setEndDate(new \DateTimeImmutable('2026-02-28'));
        self::assertSame('Payment for Plan from 31/01/2026 to 28/02/2026', $period->getPaymentDescriptor());
    }

    public function testPeriodUsesScheduledBoundaryAndClampsMonthEnd(): void
    {
        $repository = $this->createMock(SubscriptionsRepository::class);
        $repository->expects(self::once())->method('saveSubscriptionPeriod')->willReturnCallback(function (SubscriptionPeriod $period): void {
            $period->setId(1);
        });
        $service = new Subscriptions($repository, $this->createStub(Hydrator::class), new EventHelper(new EventDispatcher()), $this->createStub(Payments::class));
        $method = new \ReflectionMethod(Subscriptions::class, 'createNewPeriodForSubscription');
        $period = $method->invoke($service, $this->subscription());
        self::assertSame('2026-01-31 12:00:00', $period->getStartDate()->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-28 12:00:00', $period->getEndDate()->format('Y-m-d H:i:s'));
    }

    public function testFreeRenewalAdvancesWithoutAMandate(): void
    {
        $payments = $this->createMock(Payments::class);
        $payments->expects(self::never())->method('getProviderController');
        [$service, $connection] = $this->service($this->subscription(), $payments);
        $service->processNewSubscriptionPeriods(null, new \DateTimeImmutable('2026-02-01'));
        self::assertSame('2026-02-28 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription'));
        self::assertSame(1, (int)$connection->fetchOne('SELECT status_id FROM subscription'));
    }

    public function testMissingMandateFailsWithoutAdvancingAndReusesThePeriod(): void
    {
        $subscription = $this->subscription();
        $subscription->setPriceInPence(100);
        $payments = $this->createMock(Payments::class);
        $payments->expects(self::never())->method('getProviderController');
        [$service, $connection] = $this->service($subscription, $payments);
        $service->processNewSubscriptionPeriods(null, new \DateTimeImmutable('2026-02-01'));
        $service->processNewSubscriptionPeriods(null, new \DateTimeImmutable('2026-02-01'));
        self::assertSame('2026-01-31 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription'));
        self::assertSame(2, (int)$connection->fetchOne('SELECT status_id FROM subscription'));
        self::assertSame(1, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period'));
        self::assertStringContainsString('mandate is required', $connection->fetchOne('SELECT payment_error FROM subscription_period'));
    }

    public function testPendingPaymentIsReconciledWithoutAnotherCharge(): void
    {
        $subscription = $this->subscriptionWithMandate();
        $payment = $this->payment('pending');
        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects(self::once())->method('chargeMandate')->with($subscription->getPaymentMethod(), 100, 'Payment for Plan from 31/01/2026 to 28/02/2026')->willReturn($payment);
        $payments = $this->createStub(Payments::class);
        $payments->method('getProviderController')->willReturn($provider);
        $payments->method('getPaymentById')->willReturn($payment);
        [$service, $connection] = $this->service($subscription, $payments);
        $connection->insert('payment', ['id' => 1]);
        $now = new \DateTimeImmutable('2026-02-01');
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame('2026-01-31 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription'));
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame(1, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period'));
        $payment->setStatus($this->payment('failed')->getStatus());
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame(2, (int)$connection->fetchOne('SELECT status_id FROM subscription'));
        $payment->setStatus($this->payment('paid')->getStatus());
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame('2026-02-28 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription'));
        self::assertSame(1, (int)$connection->fetchOne('SELECT status_id FROM subscription'));
    }

    public function testProviderExceptionIsRecordedAndDoesNotStopOtherSubscriptionsOrRecharge(): void
    {
        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects(self::once())->method('chargeMandate')->willThrowException(new \RuntimeException('Gateway timed out'));
        $payments = $this->createStub(Payments::class);
        $payments->method('getProviderController')->willReturn($provider);
        [$service, $connection] = $this->service($this->subscriptionWithMandate(), $payments);
        $other = $connection->fetchAssociative('SELECT * FROM subscription WHERE id = 1');
        $other['id'] = 2;
        $other['price_in_pence'] = 0;
        $connection->insert('subscription', $other);
        $now = new \DateTimeImmutable('2026-02-01');
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame(2, (int)$connection->fetchOne('SELECT status_id FROM subscription WHERE id = 1'));
        self::assertSame('2026-02-28 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription WHERE id = 2'));
        self::assertStringContainsString('Gateway timed out', $connection->fetchOne('SELECT payment_error FROM subscription_period WHERE subscription_id = 1'));
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame(2, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period'));
    }

    public function testFailureToLinkASuccessfulChargeDoesNotCauseAnotherCharge(): void
    {
        $provider = $this->createMock(AbstractProvider::class);
        $provider->expects(self::once())->method('chargeMandate')->willReturn($this->payment('paid'));
        $payments = $this->createStub(Payments::class);
        $payments->method('getProviderController')->willReturn($provider);
        [$service, $connection] = $this->service($this->subscriptionWithMandate(), $payments);
        $now = new \DateTimeImmutable('2026-02-01');
        try {
            $service->processNewSubscriptionPeriods(null, $now);
            self::fail('The missing payment FK must prevent linking');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('could not be processed', $e->getMessage());
        }
        self::assertNotNull($connection->fetchOne('SELECT date_last_attempted FROM subscription_period'));
        self::assertSame(0, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period_payment'));
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame('2026-01-31 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription'));
        self::assertSame(1, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period'));
    }

    public function testExpiredSubscriptionIsNotChargedEvenIfBillingIsOverdue(): void
    {
        $subscription = $this->subscriptionWithMandate();
        $subscription->setEndDate(new \DateTimeImmutable('2026-02-01'));
        $payments = $this->createMock(Payments::class);
        $payments->expects(self::never())->method('getProviderController');
        [$service, $connection] = $this->service($subscription, $payments);
        $service->processNewSubscriptionPeriods(null, new \DateTimeImmutable('2026-02-02'));
        self::assertSame(4, (int)$connection->fetchOne('SELECT status_id FROM subscription'));
        self::assertSame(0, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period'));
    }

    public function testSubscriptionSaveFailureAfterChargeReusesThePaidPeriod(): void
    {
        $provider = $this->createMock(AbstractProvider::class);
        $payment = $this->payment('paid');
        $provider->expects(self::once())->method('chargeMandate')->willReturn($payment);
        $payments = $this->createStub(Payments::class);
        $payments->method('getProviderController')->willReturn($provider);
        $payments->method('getPaymentById')->willReturn($payment);
        $dispatcher = new EventDispatcher();
        $listener = static function (): void {
            throw new \RuntimeException('Subscription save interrupted');
        };
        $dispatcher->addListener(PreSubscriptionSaveEvent::class, $listener);
        [$service, $connection] = $this->service($this->subscriptionWithMandate(), $payments, $dispatcher);
        $connection->insert('payment', ['id' => 1]);
        $now = new \DateTimeImmutable('2026-02-01');
        try {
            $service->processNewSubscriptionPeriods(null, $now);
            self::fail('The event must interrupt the subscription save');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Subscription save interrupted', $e->getMessage());
        }
        self::assertSame('2026-01-31 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription'));
        $dispatcher->removeListener(PreSubscriptionSaveEvent::class, $listener);
        $service->processNewSubscriptionPeriods(null, $now);
        self::assertSame('2026-02-28 12:00:00', $connection->fetchOne('SELECT next_billing_date FROM subscription'));
        self::assertSame(1, (int)$connection->fetchOne('SELECT COUNT(*) FROM subscription_period'));
    }

    public function testMonthEndAnchorIsRestoredAndDatesAreNotMutated(): void
    {
        $subscription = $this->subscription();
        $date = new \DateTime('2026-02-28 12:00:00');
        $subscription->setNextBillingDate($date);
        self::assertSame('2026-03-31 12:00:00', (new \Pantono\Subscriptions\Model\BillingSchedule())->getPeriodEnd($subscription)->format('Y-m-d H:i:s'));
        self::assertSame('2026-02-28 12:00:00', $date->format('Y-m-d H:i:s'));
        $subscription->setEndDate(new \DateTimeImmutable('2026-03-10 12:00:00'));
        self::assertSame('2026-03-10 12:00:00', (new \Pantono\Subscriptions\Model\BillingSchedule())->getPeriodEnd($subscription)->format('Y-m-d H:i:s'));
    }

    #[DataProvider('invalidSubscriptions')]
    public function testInvalidSubscriptionDataIsRejected(string $case): void
    {
        $subscription = $this->subscription();
        switch ($case) {
            case 'negative price':
                $subscription->setPriceInPence(-1);
                break;
            case 'unsupported interval':
                $subscription->getType()->setBillingIntervalUnit('last friday');
                break;
            case 'missing type':
                $subscription->setType(null);
                break;
            case 'unsaved type':
                $subscription->getType()->setId(null);
                break;
            case 'end before start':
                $subscription->setEndDate(new \DateTimeImmutable('2026-01-01'));
                break;
            case 'billing before start':
                $subscription->setNextBillingDate(new \DateTimeImmutable('2026-01-01'));
                break;
            case 'customer not allowed':
                $subscription->getType()->setAllowCustomer(false);
                $subscription->setCustomer(new \Pantono\Customers\Model\Customer());
                break;
            case 'company not allowed':
                $subscription->getType()->setAllowCompany(false);
                $subscription->setCompany(new \Pantono\Customers\Model\Company());
                break;
        }
        $this->expectException(\InvalidArgumentException::class);
        (new \Pantono\Subscriptions\Model\BillingSchedule())->validate($subscription);
    }

    public static function invalidSubscriptions(): array
    {
        return array_map(static fn(string $case): array => [$case], ['negative price', 'unsupported interval', 'missing type', 'unsaved type', 'end before start', 'billing before start', 'customer not allowed', 'company not allowed']);
    }

    public function testInvalidIntervalIsRejectedBeforeSaving(): void
    {
        $subscription = $this->subscription();
        $subscription->getType()->setBillingIntervalCount(0);
        $repository = $this->createMock(SubscriptionsRepository::class);
        $repository->expects(self::never())->method('saveModel');
        $service = new Subscriptions($repository, $this->createStub(Hydrator::class), new EventHelper(new EventDispatcher()), $this->createStub(Payments::class));
        $this->expectException(\InvalidArgumentException::class);
        $service->saveSubscription($subscription);
    }

    public function testItemsAreValidatedAndSaveEventsFire(): void
    {
        $dispatcher = new EventDispatcher();
        $fired = false;
        $dispatcher->addListener(PostSubscriptionPeriodItemSaveEvent::class, function () use (&$fired): void {
            $fired = true;
        });
        [$service, $connection] = $this->service($this->subscription(), $this->createStub(Payments::class), $dispatcher);
        $service->processNewSubscriptionPeriods(null, new \DateTimeImmutable('2026-02-01'));
        $connection->insert('subscription_type_item', ['id' => 1, 'subscription_type_id' => 1, 'name' => 'Usage', 'quantity' => 1]);
        $typeItem = new SubscriptionTypeItem();
        $typeItem->setId(1);
        $typeItem->setSubscriptionTypeId(1);
        $period = new SubscriptionPeriod();
        $period->setId(1);
        $period->setSubscription($this->subscription());
        $period->setStartDate(new \DateTimeImmutable('2026-01-31 12:00:00'));
        $period->setEndDate(new \DateTimeImmutable('2026-02-28 12:00:00'));
        $item = $service->addItemToSubscriptionPeriod($period, $typeItem, 2, ['unit' => 'calls'], new \DateTimeImmutable('2026-02-01'));
        self::assertNotNull($item->getId());
        self::assertTrue($fired);
        self::assertSame(2, (int)$connection->fetchOne('SELECT quantity FROM subscription_period_item'));
        $this->expectException(\InvalidArgumentException::class);
        $service->addItemToSubscriptionPeriod(new SubscriptionPeriod(), $typeItem, 2, []);
    }

    #[DataProvider('invalidItems')]
    public function testInvalidItemDataIsRejected(string $case): void
    {
        $repository = $this->createMock(SubscriptionsRepository::class);
        $repository->expects(self::never())->method('saveModel');
        $service = new Subscriptions($repository, $this->createStub(Hydrator::class), new EventHelper(new EventDispatcher()), $this->createStub(Payments::class));
        $period = new SubscriptionPeriod();
        $period->setId(1);
        $period->setSubscription($this->subscription());
        $period->setStartDate(new \DateTimeImmutable('2026-01-31 12:00:00'));
        $period->setEndDate(new \DateTimeImmutable('2026-02-28 12:00:00'));
        $type = new SubscriptionTypeItem();
        $type->setId($case === 'unsaved type' ? null : 1);
        $type->setSubscriptionTypeId($case === 'wrong type' ? 2 : 1);
        $quantity = $case === 'zero quantity' ? 0 : ($case === 'negative quantity' ? -1 : 1);
        $date = new \DateTimeImmutable($case === 'before period' ? '2026-01-01' : ($case === 'at end' ? '2026-02-28 12:00:00' : '2026-02-01'));
        $this->expectException(\InvalidArgumentException::class);
        $service->addItemToSubscriptionPeriod($period, $type, $quantity, [], $date);
    }

    public static function invalidItems(): array
    {
        return array_map(static fn(string $case): array => [$case], ['unsaved type', 'wrong type', 'zero quantity', 'negative quantity', 'before period', 'at end']);
    }

    /**
     * @return array{Subscriptions, Connection}
     */
    private function service(Subscription $template, Payments $payments, ?EventDispatcher $dispatcher = null): array
    {
        $db = TestDatabase::create();
        $repository = new SubscriptionsRepository($db);
        $connection = $db->getDoctrineConnection();
        $connection->update('subscription', ['price_in_pence' => $template->getPriceInPence(), 'end_date' => $template->getEndDate()?->format('Y-m-d H:i:s')], ['id' => 1]);
        $hydrate = function (string $class, ?array $row) use ($template): ?object {
            if ($row === null) {
                return null;
            }
            if ($class === Subscription::class) {
                $subscription = clone $template;
                $subscription->setId((int)$row['id']);
                $subscription->setPriceInPence((int)$row['price_in_pence']);
                $subscription->setNextBillingDate(new \DateTimeImmutable($row['next_billing_date']));
                $status = new SubscriptionStatus();
                $status->setId((int)$row['status_id']);
                $subscription->setStatus($status);
                return $subscription;
            }
            $period = new SubscriptionPeriod();
            $period->setId((int)$row['id']);
            $period->setSubscription($template);
            $period->setStartDate(new \DateTimeImmutable($row['start_date']));
            $period->setEndDate(new \DateTimeImmutable($row['end_date']));
            $period->setPriceInPence((int)$row['price_in_pence']);
            $period->setDateLastAttempted($row['date_last_attempted'] === null ? null : new \DateTimeImmutable($row['date_last_attempted']));
            $period->setPaymentError($row['payment_error']);
            return $period;
        };
        $hydrator = $this->createStub(Hydrator::class);
        $hydrator->method('hydrate')->willReturnCallback($hydrate);
        $hydrator->method('lookupRecord')->willReturnCallback(function (string $class, int $id) use ($repository, $hydrate): ?object {
            if ($class === SubscriptionStatus::class) {
                $status = new SubscriptionStatus();
                $status->setId($id);
                return $status;
            }
            if ($class === SubscriptionPeriodItem::class) {
                return null;
            }
            return $hydrate($class, $repository->lookupRecord($class, $id));
        });
        return [new Subscriptions($repository, $hydrator, new EventHelper($dispatcher ?? new EventDispatcher()), $payments), $connection];
    }

    private function subscriptionWithMandate(): Subscription
    {
        $subscription = $this->subscription();
        $subscription->setPriceInPence(100);
        $mandate = new PaymentMandate();
        $mandate->setId(1);
        $mandate->setPaymentGateway(new PaymentGateway());
        $subscription->setPaymentMethod($mandate);
        return $subscription;
    }

    private function payment(string $state): Payment
    {
        $status = new PaymentStatus();
        $status->setCompleted($state === 'paid');
        $status->setPending($state === 'pending');
        $status->setFailed($state === 'failed');
        $payment = new Payment();
        $payment->setId(1);
        $payment->setAmount(100);
        $payment->setStatus($status);
        return $payment;
    }

    private function subscription(): Subscription
    {
        $type = new SubscriptionType();
        $type->setId(1);
        $type->setName('Plan');
        $type->setBillingIntervalUnit('month');
        $type->setBillingIntervalCount(1);
        $type->setAllowCustomer(true);
        $type->setAllowCompany(true);
        $subscription = new Subscription();
        $subscription->setId(1);
        $subscription->setType($type);
        $subscription->setStartDate(new \DateTimeImmutable('2026-01-31 12:00:00'));
        $subscription->setNextBillingDate(new \DateTimeImmutable('2026-01-31 12:00:00'));
        $subscription->setPriceInPence(0);
        return $subscription;
    }
}
