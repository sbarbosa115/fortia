<?php

namespace App\Tests\Functional\Api\Questionnaires;

/** Flow payloads of POST/PUT /questionnaire (PRD §8.4) for the functional tests of the Questionnaires context. */
trait FlowPayloads
{
    /** @return array<string, mixed> a regular questionnaire with one radio question */
    protected static function regularFlow(string $title = 'Customer survey', ?string $slug = null): array
    {
        return [
            'slug' => $slug,
            'states' => [[
                'state_id' => 'start',
                'type' => 'questionnaire',
                'parameters' => ['questionnaire' => [
                    'title' => $title,
                    'description' => 'Tell us how it went',
                    'landing_page' => true,
                    'questions' => [[
                        'title' => 'How was it?',
                        'options' => [['type' => 'radio', 'options' => [['label' => 'Good', 'value' => 'good'], ['label' => 'Bad', 'value' => 'bad']]]],
                    ]],
                ]],
                'outputs' => [],
            ]],
            'cta' => null,
        ];
    }

    /** @return array<string, mixed> a scorable diagnostic: one categorised radio worth 0–10, two tiers */
    protected static function diagnosticFlow(string $title = 'AI maturity', ?string $slug = null): array
    {
        return [
            'slug' => $slug,
            'states' => [
                [
                    'state_id' => 'start',
                    'type' => 'questionnaire',
                    'next' => 'diag',
                    'parameters' => ['questionnaire' => [
                        'title' => $title,
                        'on_completed' => [
                            'type' => 'diagnostic',
                            'tiers' => [
                                ['id' => 't1', 'name' => 'Beginner', 'min' => 0, 'max' => 4],
                                ['id' => 't2', 'name' => 'Advanced', 'min' => 5, 'max' => 10],
                            ],
                            'recommendations' => [['tier_id' => 't1', 'recommendation' => 'Start small', 'visible' => true]],
                            'action_plan' => [['tier_id' => 't2', 'action' => 'Scale up', 'visible' => true]],
                        ],
                        'questions' => [[
                            'title' => 'Do you use AI?',
                            'category' => 'Usage',
                            'options' => [['type' => 'radio', 'options' => [['label' => 'No', 'value' => 0], ['label' => 'Yes', 'value' => 10]]]],
                        ]],
                    ]],
                ],
                ['state_id' => 'diag', 'type' => 'diagnostic', 'parameters' => [], 'outputs' => []],
            ],
            'layout' => ['score', 'tier', 'recommendations'],
            'result_copy' => ['title' => 'Your result'],
        ];
    }

    /** @return array<string, mixed> a chain: the questionnaire, one prompt (its text inline) and a result */
    protected static function chainFlow(string $title = 'Discovery chain', string $promptText = 'Ask deeper questions about their goals.'): array
    {
        return [
            'slug' => null,
            'states' => [
                [
                    'state_id' => 'start',
                    'type' => 'questionnaire',
                    'next' => 'p1',
                    'parameters' => ['questionnaire' => [
                        'title' => $title,
                        'questions' => [[
                            'title' => 'What is your goal?',
                            'options' => [['type' => 'text']],
                        ]],
                    ]],
                ],
                ['state_id' => 'p1', 'type' => 'prompt', 'next' => 'end', 'parameters' => ['text' => $promptText]],
                ['state_id' => 'end', 'type' => 'result'],
            ],
        ];
    }

    /** Moves the test clock forward, e.g. '+1 minute'. */
    protected function later(string $modifier): void
    {
        $this->clock()->set($this->clock()->now()->modify($modifier)->format('Y-m-d H:i:s'));
    }

    /**
     * Creates a questionnaire through the API and returns its id.
     *
     * @param array<string, mixed> $flow
     */
    protected function createQuestionnaire(string $as, array $flow): string
    {
        $response = $this->api('POST', '/api/v1/questionnaire', $flow, as: $as);
        $data = $this->data($response, 201);

        return (string) $data['questionnaire_id'];
    }
}
