<?php

namespace App\Reporting\Domain\Model;

use App\Shared\Domain\Document\ControlType;
use App\Shared\Domain\Document\Questions;
use App\Shared\Domain\Document\Scoring;

/**
 * What a chart needs to know about a question (PRD §8.4 GET /dashboard "questions"): its id, title, control type,
 * options and scale. Built from the questionnaire's question documents; message slides have none.
 */
final class QuestionProfile
{
    /**
     * @param list<array{label: string, value: string}> $options
     */
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ControlType $type,
        public readonly array $options,
        public readonly ?float $min,
        public readonly ?float $max,
    ) {
    }

    /**
     * The profile of an answerable question, or null for a message slide or a question without a control.
     *
     * @param array<string, mixed> $question
     */
    public static function fromQuestion(array $question): ?self
    {
        if (!Questions::isAnswerable($question) || '' === (string) ($question['id'] ?? '')) {
            return null;
        }
        $control = (array) Questions::control($question);
        $type = ControlType::from((string) $control['type']);
        $options = [];
        foreach ((array) ($control['options'] ?? []) as $option) {
            if (!\is_array($option)) {
                continue;
            }
            $label = (string) ($option['label'] ?? '');
            $value = $option['value'] ?? null;
            $options[] = ['label' => $label, 'value' => null === $value || '' === $value ? $label : (string) $value];
        }
        [$min, $max] = [null, null];
        if (ControlType::Range === $type) {
            [$min, $max] = Scoring::rangeBounds($control);
        }

        return new self((string) $question['id'], (string) ($question['title'] ?? ''), $type, $options, $min, $max);
    }

    /** Radio, checkbox, select or ranking: the answer is one or more of its options. */
    public function isSelection(): bool
    {
        return $this->type->isSelection();
    }

    /** A number scale: a slider, or a single-choice control whose option values are all numbers. */
    public function isNumeric(): bool
    {
        if (ControlType::Range === $this->type) {
            return true;
        }
        if (!\in_array($this->type, [ControlType::Radio, ControlType::Select], true) || [] === $this->options) {
            return false;
        }
        foreach ($this->options as $option) {
            if (!is_numeric($option['value'])) {
                return false;
            }
        }

        return true;
    }

    /** Whether its answers are listed value by value (free text, files and contact fields are only counted). */
    public function hasCountableValues(): bool
    {
        return $this->isSelection() || ControlType::Range === $this->type;
    }

    /** The answer scale, compared by heatmaps and stacked bars (PRD §7.10): the option values or the slider range. */
    public function scale(): string
    {
        if (ControlType::Range === $this->type) {
            return 'range:'.$this->min.':'.$this->max;
        }

        return 'options:'.implode("\u{1F}", array_column($this->options, 'value'));
    }

    /** @return array{id: string, title: string, type: string, options: list<array{label: string, value: string}>, min: float|null, max: float|null} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'title' => $this->title, 'type' => $this->type->value, 'options' => $this->options, 'min' => $this->min, 'max' => $this->max];
    }
}
