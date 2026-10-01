<?php

namespace App\Assignations\UI\Cli;

use App\Assignations\Application\DailyReminders;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Runs the daily follow-up reminders now (PRD §7.13), the same run the 13:00 UTC schedule does. */
#[AsCommand(name: 'app:assignations:send-reminders', description: 'Send today\'s follow-up reminders now (PRD §7.13)')]
final class SendRemindersCommand extends Command
{
    public function __construct(private readonly DailyReminders $reminders)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $summary = $this->reminders->run();
        $output->writeln(\sprintf('Sent %d reminder(s) to %d recipient(s); skipped %d.', $summary['sent'], $summary['recipients'], $summary['skipped']));

        return Command::SUCCESS;
    }
}
