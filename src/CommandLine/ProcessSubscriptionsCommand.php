<?php

namespace Pantono\Subscriptions\CommandLine;

use Symfony\Component\Console\Command\Command;
use Pantono\Subscriptions\Subscriptions;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class ProcessSubscriptionsCommand extends Command
{
    private Subscriptions $subscriptions;

    public function __construct(Subscriptions $subscriptions)
    {
        $this->subscriptions = $subscriptions;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('subscriptions:process');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->subscriptions->processNewSubscriptionPeriods($output);
        return Command::SUCCESS;
    }
}
