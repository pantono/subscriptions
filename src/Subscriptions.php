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

class Subscriptions
{
    public const int STATUS_ACTIVE = 1;
    public const int STATUS_PAYMENT_FAILED = 2;
    public const int STATUS_CANCELLED = 3;
    public const int STATUS_EXPIRED = 4;
    private SubscriptionsRepository $repository;
    private Hydrator $hydrator;
    private EventHelper $eventHelper;

    public function __construct(SubscriptionsRepository $repository, Hydrator $hydrator, EventHelper $eventHelper)
    {
        $this->repository = $repository;
        $this->hydrator = $hydrator;
        $this->eventHelper = $eventHelper;
    }

    public function saveSubscription(Subscription $subscription): void
    {
        $previous = $subscription->getId() ? $this->hydrator->lookupRecord(Subscription::class, $subscription->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionSaveEvent::class, $subscription, $previous);

        $this->repository->saveModel($subscription);

        $this->eventHelper->fireEvent(PostSubscriptionSaveEvent::class, $subscription, $previous);
    }

    public function saveSubscriptionPeriod(SubscriptionPeriod $period): void
    {
        $previous = $period->getId() ? $this->hydrator->lookupRecord(Subscription::class, $period->getId()) : null;
        $this->eventHelper->fireEvent(PreSubscriptionPeriodSaveEvent::class, $period, $previous);

        $this->repository->saveModel($period);

        $this->eventHelper->fireEvent(PostSubscriptionPeriodSaveEvent::class, $period, $previous);
    }
}
