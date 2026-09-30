<?php

namespace App\Tests\Functional\Api\Responses;

use App\Assignations\Domain\Model\Assignation;
use App\Commerce\Domain\Model\Product;
use App\Questionnaires\Domain\Model\Diagnostic;
use App\Questionnaires\Domain\Model\Flow;
use App\Questionnaires\Domain\Model\Questionnaire;
use App\Responses\Application\Command\StartSession;
use App\Responses\Domain\Model\QuestionnaireSession;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Domain\Ids;

/**
 * Questionnaires, flows and diagnostics built straight with their entities (authoring builds the API that creates
 * them), and helpers to answer a session as the respondent app does. Used by ApiTestCase subclasses.
 */
trait SessionFixtures
{
    /**
     * @param list<array<string, mixed>>|null $questions
     * @param array<string, mixed>|null       $onCompleted
     */
    protected function questionnaire(string $customerId, string $type = 'default', ?array $questions = null, ?array $onCompleted = null, ?string $parent = null, ?string $originSessionId = null, bool $active = true): string
    {
        $id = Ids::uuid4();
        $now = new \DateTimeImmutable('2026-09-01T00:00:00Z');
        $questionnaire = new Questionnaire($id, $customerId, 'Questionnaire '.$type, $type, $questions ?? [self::textQuestion('q1', 'Tell us about you')], $now);
        $questionnaire->describe(['on_completed' => $onCompleted ?? ['type' => 'default'], 'description' => 'Desc'], $now);
        if (null !== $parent) {
            $questionnaire->makeStageOf($parent, $originSessionId);
        }
        if (!$active) {
            $questionnaire->setActive(false, $now);
        }
        $this->em()->persist($questionnaire);
        $this->em()->flush();

        return $id;
    }

    /**
     * @param list<array<string, mixed>> $states
     * @param array<string, mixed>|null  $cta
     * @param list<string>|null          $layout
     * @param array<string, string>|null $resultCopy
     */
    protected function flow(string $customerId, string $questionnaireId, array $states, ?array $cta = null, ?array $layout = null, ?array $resultCopy = null): string
    {
        $id = Ids::alphanumeric(20);
        $now = new \DateTimeImmutable('2026-09-01T00:00:00Z');
        $flow = new Flow($id, 'flow-'.strtolower(Ids::alphanumeric(8)), $customerId, $questionnaireId, $states, $now);
        $flow->redefine($flow->slug(), $states, $cta, $layout, $resultCopy, $now);
        $this->em()->persist($flow);
        $this->em()->flush();

        return $id;
    }

    /**
     * @param list<array<string, mixed>> $tiers
     * @param list<array<string, mixed>> $recommendations
     * @param list<array<string, mixed>> $actionPlan
     */
    protected function diagnostic(string $questionnaireId, array $tiers, array $recommendations = [], array $actionPlan = []): void
    {
        $this->em()->persist(new Diagnostic(Ids::uuid4(), $questionnaireId, $tiers, $recommendations, $actionPlan));
        $this->em()->flush();
    }

    protected function assignation(string $customerId, string $questionnaireId, string $type = 'default'): string
    {
        $id = Ids::uuid4();
        $this->em()->persist(new Assignation($id, $customerId, Ids::uuid4(), $questionnaireId, 'Assignment', $type, new \DateTimeImmutable('2026-09-01T00:00:00Z')));
        $this->em()->flush();

        return $id;
    }

    protected function product(string $customerId, string $name, ?string $questionnaireId = null): string
    {
        $id = Ids::uuid4();
        $at = new \DateTimeImmutable('2026-09-01T00:00:00Z');
        $product = new Product($id, $customerId, $name, $at);
        $product->describe($name, '<p>'.$name.' description</p>', '19.90', 'https://shop.test/'.$id.'.png', 'https://shop.test/p/'.$id, $at);
        $product->placeIn('https://shop.test', $questionnaireId);
        $this->em()->persist($product);
        $this->em()->flush();

        return $id;
    }

    /** @param array<string, mixed> $options StartSession's named arguments besides the questionnaire */
    protected function startSessionCommand(string $questionnaireId, array $options = []): string
    {
        return (string) static::getContainer()->get(CommandBus::class)->dispatch(new StartSession($questionnaireId, ...$options));
    }

    /** @return array<string, mixed> the bare session the respondent app receives */
    protected function startSession(string $questionnaireId): array
    {
        $response = $this->api('POST', '/api/v1/questionnaire/'.$questionnaireId.'/session');
        self::assertSame(200, $response['status'], $response['body']);
        self::assertIsArray($response['json']);

        return $response['json'];
    }

    protected function storedSession(string $sessionId): QuestionnaireSession
    {
        $this->em()->clear();
        $session = $this->em()->find(QuestionnaireSession::class, $sessionId);
        self::assertNotNull($session);

        return $session;
    }

    /**
     * The respondent's answers on a session: question id → value (or null + skipped).
     *
     * @param array<string, mixed>               $session
     * @param array<string, string|list<string>> $values
     * @param list<string>                       $skipped
     *
     * @return array<string, mixed>
     */
    protected static function answered(array $session, array $values, array $skipped = []): array
    {
        foreach ($session['questions'] as $i => $question) {
            if (\array_key_exists($question['id'], $values)) {
                $session['questions'][$i]['options'][0]['value'] = $values[$question['id']];
                $session['questions'][$i]['options'][0]['timestamp'] = '2026-09-30T10:00:00Z';
            }
            if (\in_array($question['id'], $skipped, true)) {
                $session['questions'][$i]['options'][0]['skipped'] = true;
            }
        }

        return $session;
    }

    /**
     * @param list<string> $criteria
     *
     * @return array<string, mixed>
     */
    protected static function textQuestion(string $id, string $title, int $maxFollowups = 0, array $criteria = []): array
    {
        return [
            'id' => $id, 'order' => 0, 'title' => $title, 'required' => true,
            'max_followups' => $maxFollowups, 'acceptance_criteria' => $criteria,
            'options' => [['name' => $id.'-c', 'type' => 'text', 'options' => [], 'validations' => []]],
        ];
    }

    /**
     * @param list<string> $values
     *
     * @return array<string, mixed>
     */
    protected static function radioQuestion(string $id, ?string $category, array $values = ['0', '1', '2', '3']): array
    {
        return [
            'id' => $id, 'order' => 0, 'title' => 'Question '.$id, 'category' => $category, 'required' => true,
            'options' => [['name' => $id.'-c', 'type' => 'radio', 'options' => array_map(static fn (string $v): array => ['label' => 'Option '.$v, 'value' => $v], $values), 'validations' => []]],
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    protected static function state(string $id, string $type, ?string $next = null, array $parameters = []): array
    {
        return ['state_id' => str_pad($id, 15, '0'), 'type' => $type, 'parameters' => $parameters, 'outputs' => [], 'next' => null === $next ? null : str_pad($next, 15, '0')];
    }
}
