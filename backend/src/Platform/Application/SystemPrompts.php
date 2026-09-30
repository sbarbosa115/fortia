<?php

namespace App\Platform\Application;

/**
 * The editable system prompts (PRD §6.24, §7.20, Appendix D): versioned Markdown per key, cached 5 minutes; when
 * the store fails or a key has never been edited, the platform default (config/system_prompts/<key>.md) is used.
 */
interface SystemPrompts
{
    /** The 12 keys: description and required placeholders. */
    public const KEYS = [
        'shared--basic-rules-to-create-a-questionnaire' => ['Basic rules every generated questionnaire follows.', []],
        'quiz-funnel--rules-to-create-profiling-questionnaires' => ['Quiz funnel: rules for profiling questionnaires.', []],
        'quiz-funnel--rules-to-create-product-questionnaires' => ['Quiz funnel: rules for product (experience) questionnaires.', []],
        'quiz-funnel--rules-to-recommend-products' => ['Quiz funnel: how to pick products from the catalog.', []],
        'chat--conversation-rules' => ['Chat assistant: conversation rules.', []],
        'chat--rules-to-build-questionnaires' => ['Chat assistant: how to build questionnaires.', []],
        'chain--rules-to-create-questionnaires' => ['Prompt chains: how to generate the next stage.', ['{admin_instructions}', '{base_rules}']],
        'diagnostic--rules-to-create-diagnostics' => ['Diagnostics: tiers, recommendations and action plans.', []],
        'linkedin--rules-to-create-diagnostic-questionnaires' => ['LinkedIn: diagnostic questionnaire from a profile.', []],
        'followups--rules-to-evaluate-answers' => ['Follow-ups: grading an answer against its criteria.', []],
        'styles--rules-to-extract-brand-styles' => ['Brand styles: designing styles from a website.', []],
        'dashboards--select-dashboard-type' => ['Dashboards: choosing the type and charts.', ['{dashboard_catalog}']],
    ];

    /** The text in use for a key. */
    public function get(string $key): string;

    /**
     * The text in use with its {placeholders} replaced.
     *
     * @param array<string, string> $values e.g. ['admin_instructions' => '…']
     */
    public function render(string $key, array $values = []): string;
}
