<?php

namespace App\Responses\UI\Http\Input;

use App\Responses\Application\AnswerFiles;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/** PRD §8.4 POST /answers-media/download-urls. */
final class DownloadUrlInput
{
    #[Assert\NotNull]
    #[Assert\Length(max: 1024)]
    public ?string $key = null;

    #[Assert\Choice(choices: ['inline', 'attachment'])]
    public ?string $disposition = 'attachment';

    /** @return 'inline'|'attachment' */
    public function disposition(): string
    {
        return 'inline' === $this->disposition ? 'inline' : 'attachment';
    }

    #[Assert\Callback]
    public function validateKey(ExecutionContextInterface $context): void
    {
        if (null !== $this->key && !AnswerFiles::isAnswerFileKey($this->key)) {
            $context->buildViolation('The key must have exactly 4 non-empty segments separated by "/", without "..".')->atPath('key')->addViolation();
        }
    }
}
