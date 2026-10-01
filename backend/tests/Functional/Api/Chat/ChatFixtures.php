<?php

namespace App\Tests\Functional\Api\Chat;

/**
 * Helpers of the chat tests: a turn is POST /chat and its job, which runs inside the request in tests (the queue is
 * synchronous), so the job is already finished when it is read back.
 */
trait ChatFixtures
{
    /**
     * Sends the conversation and returns the finished job.
     *
     * @param list<string>|list<array{role: string, content: string}> $messages strings alternate user / assistant
     * @param array<string, mixed>                                    $extra    mode, draft, item, pending_writes
     *
     * @return array<string, mixed>
     */
    private function turn(array $messages, array $extra = [], string $as = 'root@acme0001.test'): array
    {
        $job = $this->data($this->api('POST', '/api/v1/chat', ['messages' => self::conversation($messages)] + $extra, as: $as), 202)['job'];
        self::assertSame('chat', $job['job_type'], 'PRD §8.10: 202 {job} of type chat');

        return $this->data($this->api('GET', '/api/v1/jobs/'.$job['job_id'], as: $as))['job'];
    }

    /**
     * The result of a turn that must complete.
     *
     * @param list<string>|list<array{role: string, content: string}> $messages
     * @param array<string, mixed>                                    $extra
     *
     * @return array<string, mixed>
     */
    private function reply(array $messages, array $extra = [], string $as = 'root@acme0001.test'): array
    {
        $job = $this->turn($messages, $extra, $as);
        self::assertSame('COMPLETED', $job['status'], 'body: '.json_encode($job));

        return $job['result'];
    }

    /**
     * @param list<string>|list<array{role: string, content: string}> $messages
     *
     * @return list<array{role: string, content: string}>
     */
    private static function conversation(array $messages): array
    {
        $out = [];
        foreach ($messages as $i => $message) {
            $out[] = \is_array($message) ? $message : ['role' => 0 === $i % 2 ? 'user' : 'assistant', 'content' => $message];
        }

        return $out;
    }

    /** A regular questionnaire of the account, made through the API. */
    private function ownQuestionnaire(string $as, string $title = 'Encuesta de clima'): string
    {
        return $this->data($this->api('POST', '/api/v1/questionnaire', ['states' => [[
            'state_id' => 'start',
            'type' => 'questionnaire',
            'parameters' => ['questionnaire' => [
                'title' => $title,
                'questions' => [['title' => '¿Cómo te sientes?', 'options' => [['type' => 'radio', 'options' => [['label' => 'Bien', 'value' => 'bien'], ['label' => 'Mal', 'value' => 'mal']]]]]],
            ]],
        ]]], as: $as), 201)['questionnaire_id'];
    }
}
