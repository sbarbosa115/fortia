<?php

namespace App\Integrations\UI\Cli;

use App\Integrations\Application\RetryDueWebhooks;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Retries the due webhook deliveries now (D19), the same run the worker does every minute. */
#[AsCommand(name: 'app:webhooks:retry', description: 'Retry the webhook deliveries whose backoff has passed (D19)')]
final class RetryWebhooksCommand extends Command
{
    public function __construct(private readonly RetryDueWebhooks $retries)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(\sprintf('Attempted %d webhook delivery(ies).', $this->retries->run()));

        return Command::SUCCESS;
    }
}
