<?php

namespace Pantono\Subscriptions\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Subscriptions\Model\SubscriptionPeriod;

class AbstractSubscriptionPeriodSaveEvent extends Event
{
    private SubscriptionPeriod $current;
    private ?SubscriptionPeriod $previous = null;

    public function getCurrent(): SubscriptionPeriod
    {
        return $this->current;
    }

    public function setCurrent(SubscriptionPeriod $current): void
    {
        $this->current = $current;
    }

    public function getPrevious(): ?SubscriptionPeriod
    {
        return $this->previous;
    }

    public function setPrevious(?SubscriptionPeriod $previous): void
    {
        $this->previous = $previous;
    }
}
