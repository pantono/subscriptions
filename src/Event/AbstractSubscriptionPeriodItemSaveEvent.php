<?php

namespace Pantono\Subscriptions\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Subscriptions\Model\SubscriptionPeriodItem;

class AbstractSubscriptionPeriodItemSaveEvent extends Event
{
    private SubscriptionPeriodItem $current;
    private ?SubscriptionPeriodItem $previous = null;

    public function getCurrent(): SubscriptionPeriodItem
    {
        return $this->current;
    }

    public function setCurrent(SubscriptionPeriodItem $current): void
    {
        $this->current = $current;
    }

    public function getPrevious(): ?SubscriptionPeriodItem
    {
        return $this->previous;
    }

    public function setPrevious(?SubscriptionPeriodItem $previous): void
    {
        $this->previous = $previous;
    }
}
