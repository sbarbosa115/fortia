<?php

namespace App\Shared\UI\Cli;

use App\Shared\Application\Seed\DemoSeeder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Seeds the demo data of every context (dev and the regression suite). */
#[AsCommand(name: 'app:seed-demo', description: 'Seed the demo accounts, catalog and sample data (dev only)')]
final class SeedDemoCommand extends Command
{
    /** @param iterable<DemoSeeder> $seeders */
    public function __construct(
        #[AutowireIterator('app.demo_seeder', defaultPriorityMethod: 'priority')]
        private readonly iterable $seeders,
        private readonly EntityManagerInterface $em,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('prod' === $this->environment) {
            $io->error('Demo data is never seeded in production.');

            return Command::FAILURE;
        }
        foreach ($this->seeders as $seeder) {
            $io->writeln(\sprintf('Seeding %s…', $seeder::class));
            $seeder->seed();
            $this->em->flush();
        }
        $io->success('Demo data seeded. Sign in with any demo account and the password "password123".');

        return Command::SUCCESS;
    }
}
