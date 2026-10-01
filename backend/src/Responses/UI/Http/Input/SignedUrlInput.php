<?php

namespace App\Responses\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** PRD §8.4 POST /signed-urls (template: a file question's template, written in the console). */
final class SignedUrlInput
{
    public const ANSWER_MEDIA = 'answer_media';
    public const PROMPT = 'prompt';
    public const TEMPLATE = 'template';

    #[Assert\NotNull]
    #[Assert\Length(min: 1, max: 255)]
    public ?string $filename = null;

    #[Assert\NotNull]
    #[Assert\Regex(pattern: '#^[\w.+-]+/[\w.+-]+$#', message: 'The content type must look like "type/subtype".')]
    #[Assert\Length(max: 255)]
    public ?string $content_type = null;

    #[Assert\NotNull]
    #[Assert\Length(min: 1, max: 16)]
    public ?string $customer_id = null;

    #[Assert\Choice(choices: [self::ANSWER_MEDIA, self::PROMPT, self::TEMPLATE])]
    public ?string $upload_type = self::ANSWER_MEDIA;

    public ?string $session_id = null;

    public ?string $question_id = null;

    public function uploadType(): string
    {
        return $this->upload_type ?? self::ANSWER_MEDIA;
    }

    /** session_id and question_id are required for an answer's file. */
    #[Assert\Callback]
    public function validateAnswerMedia(ExecutionContextInterface $context): void
    {
        if (self::ANSWER_MEDIA !== $this->uploadType()) {
            return;
        }
        foreach (['session_id' => $this->session_id, 'question_id' => $this->question_id] as $field => $value) {
            if (null === $value || '' === trim($value)) {
                $context->buildViolation('This value should not be blank.')->atPath($field)->addViolation();
            }
        }
    }
}
