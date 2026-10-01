<?php

namespace App\Integrations\Application;

/**
 * The body of the questionnaire.completed webhook (PRD §7.14, §16.1 #3 keeps it):
 * {customer_id, event_type, questionnaire_id, data: {id, answers: [{title, value, min?, max?}]}}.
 */
final class WebhookPayload
{
    public const QUESTIONNAIRE_COMPLETED = 'questionnaire.completed';

    /**
     * @param list<array<string, mixed>> $answers in the §7.14 value format (SessionQueries::answersOf)
     *
     * @return array<string, mixed>
     */
    public static function questionnaireCompleted(string $customerId, string $questionnaireId, string $sessionId, array $answers): array
    {
        return [
            'customer_id' => $customerId,
            'event_type' => self::QUESTIONNAIRE_COMPLETED,
            'questionnaire_id' => $questionnaireId,
            'data' => ['id' => $sessionId, 'answers' => $answers],
        ];
    }

    /**
     * The exact bytes sent and signed. The keys are put back in the documented order, because the JSON column the
     * delivery log keeps the payload in does not preserve it (a retry sends the same shape as the first attempt).
     *
     * @param array<string, mixed> $payload
     */
    public static function body(array $payload): string
    {
        $data = (array) ($payload['data'] ?? []);
        $answers = array_map(static function (mixed $answer): array {
            $answer = (array) $answer;
            $ordered = ['title' => $answer['title'] ?? null, 'value' => $answer['value'] ?? null];
            foreach (['min', 'max'] as $key) {
                if (\array_key_exists($key, $answer)) {
                    $ordered[$key] = $answer[$key];
                }
            }

            return $ordered;
        }, array_values((array) ($data['answers'] ?? [])));
        $ordered = [
            'customer_id' => $payload['customer_id'] ?? null,
            'event_type' => $payload['event_type'] ?? null,
            'questionnaire_id' => $payload['questionnaire_id'] ?? null,
            'data' => ['id' => $data['id'] ?? null, 'answers' => $answers],
        ];

        return json_encode($ordered, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR);
    }
}
