<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Domain\Model\QuestionProfile;
use App\Shared\Domain\Document\ControlType;
use OpenApi\Attributes as OA;

/** A question as the dashboard's charts use it: its control type, options (value = label when it has none) and scale. */
final class DashboardQuestionOutput
{
    /** @param list<array{label: string, value: string}> $options */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        #[OA\Property(enum: ControlType::VALUES)]
        public readonly string $type,
        #[OA\Property(type: 'array', items: new OA\Items(type: 'object', required: ['label', 'value'], properties: [
            new OA\Property(property: 'label', type: 'string'),
            new OA\Property(property: 'value', type: 'string'),
        ]))]
        public readonly array $options,
        public readonly ?float $min,
        public readonly ?float $max,
    ) {
    }

    public static function of(QuestionProfile $q): self
    {
        return new self($q->id, $q->title, $q->type->value, $q->options, $q->min, $q->max);
    }
}
