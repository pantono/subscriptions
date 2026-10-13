<?php

namespace Pantono\Subscriptions\Filter;

use Pantono\Contracts\Filter\PageableInterface;
use Pantono\Database\Traits\Pageable;
use Pantono\Database\Filter\SortableFilter;

class SubscriptionTypeFilter extends SortableFilter implements PageableInterface
{
    use Pageable;

    private ?bool $active = null;
    private ?string $search = null;

    public function getSortableFields(): array
    {
        return [
            'id', 'name', 'description', 'price_in_pence', 'billing_interval_count', 'billing_interval_unit'
        ];
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }

    public function getSearch(): ?string
    {
        return $this->search;
    }

    public function setSearch(?string $search): void
    {
        $this->search = $search;
    }
    
}
