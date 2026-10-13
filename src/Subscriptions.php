<?php

namespace Pantono\Subscriptions;

use Pantono\Subscriptions\Repository\SubscriptionsRepository;
use Pantono\Hydrator\Hydrator;
use Pantono\Core\Events\EventHelper;
use Pantono\Subscriptions\Event\PreSubscriptionSaveEvent;
use Pantono\Subscriptions\Model\Subscription;
use Pantono\Subscriptions\Event\PostSubscriptionSaveEvent;
use Pantono\Subscriptions\Model\SubscriptionPeriod;
use Pantono\Subscriptions\Event\PreSubscriptionPeriodSaveEvent;
use Pantono\Subscriptions\Event\PostSubscriptionPeriodSaveEvent;
use Pantono\Subscriptions\Filter\SubscriptionFilter;
use Pantono\Subscriptions\Filter\SubscriptionPeriodFilter;
use Symfony\Component\Console\Output\OutputInterface;
use Pantono\Subscriptions\Model\SubscriptionStatus;
use Pantono\Payments\Payments;
use Pantono\Subscriptions\Model\SubscriptionPeriodItem;
use Pantono\Subscriptions\Model\SubscriptionTypeItem;
use Pantono\Subscriptions\Event\PreSubscriptionPeriodItemSaveEvent;
use Pantono\Subscriptions\Event\PostSubscriptionPeriodItemSaveEvent;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Pantono\Subscriptions\Model\BillingSchedule;
use InvalidArgumentException;
use DateTimeImmutable;
use RuntimeException;
use Pantono\Subscriptions\Filter\SubscriptionTypeFilter;
use Pantono\Subscriptions\Model\SubscriptionType;

class Subscriptions
{
    public const int STATUS_ACTIVE = 1;
    public const int STATUS_PAYMENT_FAILED = 2;
    public const int STATUS_CANCELLED = 3;
    public const int STATUS_EXPIRED = 4;
    private SubscriptionsRepository $repository;
    private Hydrator $hydrator;
    private EventHelper $eventHelper;
    private Payments $payments;

    public function __construct(SubscriptionsRepository $repository, Hydrator $hydrator, EventHelper $eventHelper, Payments $payments)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->eventHelper = $eventHelper;
        $this->payments = $payments;
    }

    /**
     * @return SubscriptionType[]
     */
    public function getSubscriptionTypesByFilter(SubscriptionTypeFilter $filter): array
    {
        return $this->hydrator->hydrateSet(SubscriptionType::class, $this->repository->getSubscriptionTypesByFilter($filter));
    }

    public function saveSubscription(Subscription $subscription): void
    {
        $previous = $subscription->getId() ? $this->hydrator->lookupRecord(Subscription::class, $subscription->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionSaveEvent::class, $subscription, $previous);
        new BillingSchedule()->validate($subscription);
        if ($subscription->getStatus() === null || $subscription->getStatus()->getId() === null) {
            throw new InvalidArgumentException('A saved subscription status is required');
        }
        if ($subscription->getId() === null) {
            $subscription->setDateCreated(new DateTimeImmutable());
        }
        $subscription->setDateUpdated(new DateTimeImmutable());
        $this->repository->saveModel($subscription);

        $this->eventHelper->fireEvent(PostSubscriptionSaveEvent::class, $subscription, $previous);
    }

    public function saveSubscriptionPeriod(SubscriptionPeriod $period): void
    {
        $previous = $period->getId() ? $this->hydrator->lookupRecord(SubscriptionPeriod::class, $period->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionPeriodSaveEvent::class, $period, $previous);
        if ($period->getSubscription() === null || $period->getSubscription()->getId() === null || $period->getPriceInPence() < 0 || $period->getEndDate() <= $period->getStartDate()) {
            throw new InvalidArgumentException('A period needs a saved subscription, a nonnegative price and valid dates');
        }
        $this->repository->saveSubscriptionPeriod($period);

        $this->eventHelper->fireEvent(PostSubscriptionPeriodSaveEvent::class, $period, $previous);
    }

    public function saveSubscriptionPeriodItem(SubscriptionPeriodItem $item): void
    {
        $previous = $item->getId() ? $this->hydrator->lookupRecord(SubscriptionPeriodItem::class, $item->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionPeriodItemSaveEvent::class, $item, $previous);
        $period = $this->hydrator->lookupRecord(SubscriptionPeriod::class, $item->getPeriodId());
        if (!$period instanceof SubscriptionPeriod || $item->getType() === null) {
            throw new InvalidArgumentException('A saved period and item type are required');
        }
        $this->validatePeriodItem($period, $item->getType(), $item->getQuantity(), $item->getDate());
        $this->repository->saveModel($item);

        $this->eventHelper->fireEvent(PostSubscriptionPeriodItemSaveEvent::class, $item, $previous);
    }

    /**
     * @return Subscription[]
     */
    public function getSubscriptionsByFilter(SubscriptionFilter $filter): array
    {
        return $this->hydrator->hydrateSet(Subscription::class, $this->repository->getSubscriptionsByFilter($filter));
    }

    /**
     * @return SubscriptionPeriod[]
     */
    public function getSubscriptionPeriodsByFilter(SubscriptionPeriodFilter $filter): array
    {
        return $this->hydrator->hydrateSet(SubscriptionPeriod::class, $this->repository->getSubscriptionPeriodsByFilter($filter));
    }

    public function processNewSubscriptionPeriods(?OutputInterface $output = null, ?\DateTimeInterface $now = null): void
    {
        $now = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable());
        $afterId = 0;
        $errors = [];
        while ($rows = $this->repository->getDueSubscriptions($now, $afterId)) {
            foreach ($rows as $row) {
                $afterId = (int)$row['id'];
                try {
                    $subscription = $this->hydrator->hydrate(Subscription::class, $row);
                    if (!$subscription instanceof Subscription) {
                        throw new RuntimeException('Unable to hydrate subscription ' . $afterId);
                    }
                    $output?->writeln('Processing subscription ' . $afterId);
                    $this->processSubscription($subscription, $now, $output);
                } catch (\Throwable $e) {
                    $errors[] = $e;
                    $output?->writeln('Subscription ' . $afterId . ': ' . $e->getMessage());
                }
            }
        }
        if ($errors !== []) {
            throw new RuntimeException(count($errors) . ' subscription(s) could not be processed; first error: ' . $errors[0]->getMessage(), 0, $errors[0]);
        }
    }

    private function processSubscription(Subscription $subscription, DateTimeImmutable $now, ?OutputInterface $output): void
    {
        new BillingSchedule()->validate($subscription);
        $expectedDate = DateTimeImmutable::createFromInterface($subscription->getNextBillingDate());
        $statusId = self::STATUS_ACTIVE;
        if ($subscription->getEndDate() !== null && $subscription->getEndDate() <= $now) {
            $statusId = self::STATUS_EXPIRED;
        } else {
            $period = $this->createNewPeriodForSubscription($subscription, $now);
            if ($period->isPaid()) {
                $subscription->setNextBillingDate($period->getEndDate());
            } elseif (!$period->isPendingPaid()) {
                $statusId = self::STATUS_PAYMENT_FAILED;
                $output?->writeln('Subscription ' . $subscription->getId() . ': ' . ($period->getPaymentError() ?? 'Payment failed; reconciliation is required before retrying'));
            }
        }
        $status = $this->hydrator->lookupRecord(SubscriptionStatus::class, $statusId);
        if (!$status instanceof SubscriptionStatus) {
            throw new RuntimeException('Subscription status ' . $statusId . ' does not exist');
        }
        $previous = $this->hydrator->lookupRecord(Subscription::class, $subscription->getId());
        $subscription->setStatus($status);
        $subscription->setDateUpdated($now);
        $this->eventHelper->fireEvent(PreSubscriptionSaveEvent::class, $subscription, $previous);
        (new BillingSchedule())->validate($subscription);
        if ($this->repository->saveRenewalSubscription($subscription, $expectedDate)) {
            $this->eventHelper->fireEvent(PostSubscriptionSaveEvent::class, $subscription, $previous);
        }
    }

    private function createNewPeriodForSubscription(Subscription $subscription, ?\DateTimeInterface $now = null): SubscriptionPeriod
    {
        new BillingSchedule()->validate($subscription);
        if ($subscription->getId() === null) {
            throw new InvalidArgumentException('Renewals require a saved subscription');
        }
        $period = $this->loadRenewalPeriod($subscription);
        if ($period === null) {
            $period = new SubscriptionPeriod();
            $period->setSubscription($subscription);
            $period->setStartDate(DateTimeImmutable::createFromInterface($subscription->getNextBillingDate()));
            $period->setEndDate(new BillingSchedule()->getPeriodEnd($subscription));
            $period->setPriceInPence($subscription->getPriceInPence());
            try {
                $this->saveSubscriptionPeriod($period);
            } catch (UniqueConstraintViolationException $e) {
                $period = $this->loadRenewalPeriod($subscription);
                if ($period === null) {
                    throw $e;
                }
            }
        }
        if ($period->getPriceInPence() === 0 || $period->getPayments() !== [] || $period->getDateLastAttempted() !== null) {
            return $period;
        }
        $mandate = $subscription->getPaymentMethod();
        if ($mandate === null) {
            $period->setPaymentError('A payment mandate is required for a non-free subscription');
            $this->repository->recordPeriodPaymentError($period->getId(), $period->getPaymentError());
            return $period;
        }
        $attempted = DateTimeImmutable::createFromInterface($now ?? new DateTimeImmutable());
        if (!$this->repository->claimPeriodPayment($period->getId(), $attempted)) {
            return $this->loadRenewalPeriod($subscription) ?? $period;
        }
        $period->setDateLastAttempted($attempted);
        try {
            $controller = $this->payments->getProviderController($mandate->getPaymentGateway());
            $payment = $controller->chargeMandate($mandate, $period->getPriceInPence(), $period->getPaymentDescriptor());
        } catch (\Throwable $e) {
            $period->setPaymentError('Payment outcome requires reconciliation: ' . $e->getMessage());
            $this->repository->recordPeriodPaymentError($period->getId(), $period->getPaymentError());
            return $period;
        }
        $period->setPaymentError(null);
        $period->setPayments([$payment]);
        $this->saveSubscriptionPeriod($period);
        return $period;
    }

    private function loadRenewalPeriod(Subscription $subscription): ?SubscriptionPeriod
    {
        $row = $this->repository->getRenewalPeriod($subscription->getId(), $subscription->getNextBillingDate());
        if ($row === null) {
            return null;
        }
        $period = $this->hydrator->hydrate(SubscriptionPeriod::class, $row);
        if (!$period instanceof SubscriptionPeriod) {
            throw new RuntimeException('Unable to hydrate renewal period');
        }
        $period->setSubscription($subscription);
        $payments = [];
        foreach ($this->repository->getPeriodPaymentIds($period->getId()) as $id) {
            $payment = $this->payments->getPaymentById($id);
            if ($payment === null) {
                throw new RuntimeException('Linked payment ' . $id . ' does not exist');
            }
            $payments[] = $payment;
        }
        $period->setPayments($payments);
        return $period;
    }

    public function addItemToSubscriptionPeriod(SubscriptionPeriod $period, SubscriptionTypeItem $typeItem, int $quantity, array $metadata, ?\DateTimeInterface $date = null): SubscriptionPeriodItem
    {
        $date ??= new DateTimeImmutable();
        $this->validatePeriodItem($period, $typeItem, $quantity, $date);
        $item = new SubscriptionPeriodItem();
        $item->setDate($date);
        $item->setMetadata($metadata);
        $item->setPeriodId($period->getId());
        $item->setType($typeItem);
        $item->setQuantity($quantity);
        $this->saveSubscriptionPeriodItem($item);
        return $item;
    }

    private function validatePeriodItem(SubscriptionPeriod $period, SubscriptionTypeItem $typeItem, int $quantity, \DateTimeInterface $date): void
    {
        if ($period->getId() === null || $typeItem->getId() === null) {
            throw new InvalidArgumentException('A saved period and item type are required');
        }
        $type = $period->getSubscription()?->getType();
        if ($type === null || $type->getId() !== $typeItem->getSubscriptionTypeId()) {
            throw new InvalidArgumentException('The item does not belong to this subscription type');
        }
        if ($quantity <= 0 || $date < $period->getStartDate() || $date >= $period->getEndDate()) {
            throw new InvalidArgumentException('Item quantity must be positive and its date must be within the period');
        }
    }
}
