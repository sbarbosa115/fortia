<?php

namespace App\Generation\Application;

use App\Generation\Domain\ModelQuestionnaire;
use App\Generation\Domain\TierBands;
use App\Shared\Application\Llm\LanguageModel;
use App\Shared\Application\Llm\LlmAttachment;
use App\Shared\Application\Llm\LlmMessage;
use App\Shared\Application\Llm\LlmRequest;
use App\Shared\Application\Llm\LlmUnavailable;
use App\Shared\Domain\Error\UpstreamFailed;
use Psr\Log\LoggerInterface;

/**
 * Asks the language model for a questionnaire, up to 3 attempts (PRD §7.8): an attempt is retried when the model
 * fails, when no usable question comes back (§7.6), when a scored questionnaire has nothing to score, or when any
 * tier comes without recommendations. The tier bands are computed here, never by the model.
 */
final class QuestionnaireWriter
{
    public const ATTEMPTS = 3;

    public function __construct(
        private readonly LanguageModel $llm,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<LlmAttachment>        $attachments
     * @param array<string, mixed>       $context          for the offline fake only
     * @param list<array<string, mixed>> $earlierQuestions questions of the earlier stages scored together with these
     *
     * @return array{title: string, description: string|null, questions: list<array<string, mixed>>, diagnostic: array{tiers: list<array<string, mixed>>, recommendations: list<array<string, mixed>>, action_plan: list<array<string, mixed>>}|null}
     *
     * @throws UpstreamFailed GENERATION_FAILED after the last attempt
     */
    public function write(string $purpose, string $system, string $user, string $fallbackTitle, bool $scored, bool $withTiers, array $attachments = [], array $context = [], array $earlierQuestions = []): array
    {
        $request = new LlmRequest(
            purpose: $purpose,
            system: $system,
            messages: [LlmMessage::user($user)],
            jsonSchema: ModelQuestionnaire::schema($withTiers),
            tier: LlmRequest::TIER_GENERATION,
            attachments: $attachments,
            context: $context + ['scored' => $scored, 'tiers' => $withTiers],
        );

        for ($attempt = 1; $attempt <= self::ATTEMPTS; ++$attempt) {
            try {
                $json = $this->llm->complete($request)->json;
                $questions = ModelQuestionnaire::questions($json, $scored);
                $maxScore = ModelQuestionnaire::maxScore([...$earlierQuestions, ...$questions]);
                if ($scored && $maxScore <= 0) {
                    throw new UpstreamFailed('GENERATION_FAILED', 'Nothing in the generated questionnaire can be scored.');
                }
                $tiers = \is_array($json['tiers'] ?? null) ? array_values(array_filter($json['tiers'], 'is_array')) : [];
                if ($withTiers && !TierBands::usable($tiers)) {
                    throw new UpstreamFailed('GENERATION_FAILED', 'A generated tier came without recommendations.');
                }

                return [
                    'title' => ModelQuestionnaire::title($json, $fallbackTitle),
                    'description' => ModelQuestionnaire::description($json),
                    'questions' => $questions,
                    'diagnostic' => $withTiers ? TierBands::diagnostic($tiers, $maxScore) : null,
                ];
            } catch (LlmUnavailable|UpstreamFailed $e) {
                $this->logger->warning('Generation attempt {attempt} of {purpose} failed: {message}', ['attempt' => $attempt, 'purpose' => $purpose, 'message' => $e->getMessage()]);
            }
        }

        throw new UpstreamFailed('GENERATION_FAILED', 'The questionnaire could not be generated. Please try again.');
    }
}
