<?php

namespace Pantono\Subscriptions\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Pantono\Subscriptions\Model\Subscription;

class AbstractSubscriptionSaveEvent extends Event
{
    private Subscription $current;
    private ?Subscription $previous = null;

    public function getCurrent(): Subscription
    {
        return $this->current;
    }

    public function setCurrent(Subscription $current): void
    {
        $this->current = $current;
    }

    public function getPrevious(): ?Subscription
    {
        return $this->previous;
    }

    public function setPrevious(?Subscription $previous): void
    {
        $this->previous = $previous;
    }
}
