<?php

namespace App\Assignations\UI\Http\Input;

use Symfony\Component\Validator\Constraints as Assert;

/** PUT /assignations/{id}/reviews/{question_id} (PRD §8.8): the decision and an optional comment (empty → null). */
final class ReviewInput
{
    #[Assert\NotNull]
    #[Assert\Choice(choices: ['approved', 'rejected'])]
    public ?string $status = null;

    #[Assert\Length(max: 1000)]
    public ?string $comment = null;
}
