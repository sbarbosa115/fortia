<?php

namespace App\Questionnaires\Infrastructure\Seed;

use App\Questionnaires\Application\Command\SaveFlow;
use App\Questionnaires\Domain\Repository\FlowRepository;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Seed\DemoAccounts;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Many tagged questionnaires in one account, to try the pickers with a realistic volume (dev only): by default 57
 * questionnaires over 30 tags in Acme. The first tags are on more questionnaires than the last ones, every tenth
 * questionnaire has none. Seeded through SaveFlow, like the console; skips a slug that already exists, so it can run
 * again.
 */
#[AsCommand(name: 'app:seed-questionnaires', description: 'Seed many tagged questionnaires in one account (dev only)')]
final class SeedBulkQuestionnairesCommand extends Command
{
    private const TAGS = [
        'MatchCode', 'RRHH', 'Ventas', 'Onboarding', 'Legal', 'Q3', 'Q4', 'Clima laboral', 'Liderazgo', 'Finanzas',
        'Logística', 'Compras', 'Calidad', 'Seguridad', 'TI', 'Marketing', 'Atención al cliente', 'Operaciones',
        'Auditoría', 'Proveedores', 'Inventario', 'Formación', 'Cumplimiento', 'Riesgos', 'Innovación', 'Producto',
        'Soporte', 'Expansión', 'Diagnóstico', 'Satisfacción',
    ];
    private const SUBJECTS = [
        'Auditoría de tienda', 'Encuesta de clima', 'Diagnóstico de procesos', 'Evaluación de proveedores',
        'Plantilla de personal', 'Feedback de producto', 'Revisión de inventario', 'Mapeo de compras',
        'Chequeo de seguridad', 'Plan de formación', 'Control de calidad', 'Satisfacción del cliente',
    ];
    private const COMPANIES = ['Empresa X', 'Empresa Y', 'Empresa Z', 'Norte', 'Sur'];

    public function __construct(
        private readonly CommandBus $commands,
        private readonly FlowRepository $flows,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('count', null, InputOption::VALUE_REQUIRED, 'How many questionnaires', '57')
            ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'How many different tags (at most 30)', '30')
            ->addOption('customer', null, InputOption::VALUE_REQUIRED, 'The account', DemoAccounts::ACME);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ('prod' === $this->environment) {
            $io->error('Sample data is never seeded in production.');

            return Command::FAILURE;
        }
        $count = max(1, (int) $input->getOption('count'));
        $tags = \array_slice(self::TAGS, 0, max(1, min(\count(self::TAGS), (int) $input->getOption('tags'))));
        $customerId = (string) $input->getOption('customer');

        $created = 0;
        for ($i = 0; $i < $count; ++$i) {
            $slug = \sprintf('bulk-%s-%03d', strtolower($customerId), $i + 1);
            if (null !== $this->flows->findBySlug($slug)) {
                continue;
            }
            $title = \sprintf('%s — %s %d', self::SUBJECTS[$i % \count(self::SUBJECTS)], self::COMPANIES[$i % \count(self::COMPANIES)], $i + 1);
            $this->commands->dispatch(new SaveFlow(
                $customerId,
                self::states($title, 1 + ($i * 13) % 12),
                $slug,
                source: 'seed',
                tags: self::tagsOf($i, $tags),
            ));
            ++$created;
        }
        $io->success(\sprintf('%d questionnaires created in %s (%d already there), over %d tags.', $created, $customerId, $count - $created, \count($tags)));

        return Command::SUCCESS;
    }

    /**
     * Questionnaire $i's tags: one of the first six (the most used), one of the rest, and every third one a third.
     *
     * @param list<string> $tags
     *
     * @return list<string>
     */
    private static function tagsOf(int $i, array $tags): array
    {
        if (9 === $i % 10) {
            return [];
        }
        $n = \count($tags);
        $picked = [$tags[$i % min(6, $n)]];
        if ($n > 6) {
            $picked[] = $tags[6 + ($i * 7) % ($n - 6)];
        }
        if (0 === $i % 3) {
            $picked[] = $tags[($i * 5) % $n];
        }

        return array_values(array_unique($picked));
    }

    /** @return list<array<string, mixed>> */
    private static function states(string $title, int $questions): array
    {
        return [[
            'state_id' => 'start',
            'type' => 'questionnaire',
            'parameters' => ['questionnaire' => [
                'title' => $title,
                'landing_page' => true,
                'on_completed' => null,
                'questions' => array_map(static fn (int $n): array => [
                    'title' => "Pregunta $n",
                    'options' => [['type' => 'radio', 'options' => [
                        ['label' => 'Sí', 'value' => 'yes'],
                        ['label' => 'No', 'value' => 'no'],
                    ]]],
                ], range(1, $questions)),
            ]],
        ]];
    }
}
