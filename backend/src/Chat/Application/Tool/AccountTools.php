<?php

namespace App\Chat\Application\Tool;

use App\Branding\Application\Command\RequestStyles;
use App\Branding\Application\Query\StylesQueries;
use App\Identity\Application\Command\ChangeSettings;
use App\Identity\Application\Query\AccountQueries;
use App\Shared\Application\Bus\CommandBus;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\NotFound;

/**
 * PRD §7.19 "Account": get_profile, get_account_settings, update_account_language, update_account_settings,
 * extract_brand_styles and list_team_users. Inviting users is deliberately not offered.
 *
 * The checks are those of PRD §8.3/§8.5: changing settings is AG on the caller's own account; the
 * styles job is AG, and it runs in the background (its job id
 * goes back to the console, which follows it).
 */
final class AccountTools implements ChatToolbox
{
    private const LANGUAGES = ['es' => 'es-CO', 'en' => 'en-US'];
    private const TEXT_SETTINGS = ['pixel_id', 'linkedin_partner_id', 'linkedin_conversion_id', 'google_ads_id', 'google_ads_conversion_label'];

    public function __construct(
        private readonly AccountQueries $accounts,
        private readonly StylesQueries $styles,
        private readonly CommandBus $commands,
    ) {
    }

    public function tools(): array
    {
        $settings = [
            'transcription_url' => Schema::nullableString('The URL of the real-time transcription service.'),
            'max_files' => Schema::int('How many files a respondent may attach (1 to 20).'),
        ];
        foreach (self::TEXT_SETTINGS as $field) {
            $settings[$field] = Schema::nullableString("The $field marketing setting (up to 64 characters).");
        }

        return [
            ChatTool::read(
                'get_profile',
                'The signed-in user and their account: name, email, language, website and brand.',
                [],
                [],
                function (Caller $caller): array {
                    $account = $this->account($caller);
                    $brand = $this->styles->of($caller->customerId);

                    return [
                        'name' => $caller->name,
                        'email' => $caller->email,
                        'role' => $caller->displayedRole(),
                        'customer_id' => $account['customer_id'],
                        'language' => $account['language'],
                        'workspace_name' => $account['workspace_name'],
                        'website' => $brand['website'] ?? $account['website'],
                        'has_brand_styles' => null !== $brand && [] !== $brand['styles'],
                    ];
                },
            ),
            ChatTool::read(
                'get_account_settings',
                'The account\'s settings: language, transcription URL, marketing pixels and the files a respondent may attach.',
                [],
                [],
                fn (Caller $caller): array => ['language' => $this->account($caller)['language']] + $this->account($caller)['settings'],
            ),
            ChatTool::read(
                'list_team_users',
                'The account\'s team users and their roles.',
                Schema::paging(),
                [],
                function (Caller $caller, ToolInput $input): array {
                    $users = $this->accounts->usersOf($caller->customerId);
                    $offset = $input->offset();

                    return Schema::page(array_map(static fn (array $u): array => ['name' => $u['name'], 'email' => $u['email'], 'role' => $u['role'], 'root' => $u['root']], \array_slice($users, $offset, $input->pageSize())), $offset, \count($users));
                },
            ),
            ChatTool::write(
                'update_account_language',
                'Changes the account\'s language (es or en); the console switches to it.',
                ['language' => Schema::enum(['es', 'en'], 'Spanish (es) or English (en).')],
                ['language'],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::adminGroups($caller);
                    $this->account($caller);

                    return $input->choice('language', ['es', 'en']);
                },
                function (Caller $caller, ToolInput $input): array {
                    $language = $input->choice('language', ['es', 'en']);
                    $this->commands->dispatch(new ChangeSettings($caller->customerId, ['language' => self::LANGUAGES[$language]]));

                    return ['language' => $language];
                },
            ),
            ChatTool::write(
                'update_account_settings',
                'Changes account settings: only the fields sent.',
                $settings,
                [],
                function (Caller $caller, ToolInput $input): string {
                    Permissions::adminGroups($caller);
                    $this->account($caller);
                    $fields = self::settings($input);

                    return implode(', ', array_keys($fields));
                },
                function (Caller $caller, ToolInput $input): array {
                    $this->commands->dispatch(new ChangeSettings($caller->customerId, self::settings($input)));

                    return ['changed' => array_keys(self::settings($input))];
                },
            ),
            ChatTool::write(
                'extract_brand_styles',
                'Reads the brand (colors, font, logo) from a website and saves it as the account\'s styles, in the background (about a minute).',
                ['website' => Schema::string('The website, starting with http:// or https://.', 2048)],
                ['website'],
                static function (Caller $caller, ToolInput $input): string {
                    Permissions::adminGroups($caller);

                    return self::website($input);
                },
                function (Caller $caller, ToolInput $input): array {
                    $jobId = (string) $this->commands->dispatch(new RequestStyles($caller->customerId, self::website($input), null));

                    return ['job_id' => $jobId, 'website' => self::website($input)];
                },
            ),
        ];
    }

    /** @return array{customer_id: string, language: string, settings: array<string, mixed>, workspace_name: string|null, website: string|null} */
    private function account(Caller $caller): array
    {
        $account = $this->accounts->find($caller->customerId);
        if (null === $account) {
            throw new NotFound('CUSTOMER_NOT_FOUND', 'The account does not exist.');
        }

        return $account;
    }

    /**
     * The settings sent, shape-checked as PATCH /customer/{id}/settings does (the language has its own tool).
     *
     * @return array<string, mixed>
     */
    private static function settings(ToolInput $input): array
    {
        $fields = [];
        if ($input->has('transcription_url')) {
            $url = $input->optionalString('transcription_url', 2048);
            if (null !== $url && !self::isUrl($url, ['http', 'https'])) {
                throw ToolInput::invalid('transcription_url', 'This value is not a valid URL.');
            }
            $fields['transcription_url'] = $url;
        }
        foreach (self::TEXT_SETTINGS as $field) {
            if ($input->has($field)) {
                $fields[$field] = $input->optionalString($field, 64);
            }
        }
        if ($input->has('max_files')) {
            $fields['max_files'] = $input->int('max_files', 1, 1, 20);
        }
        if ([] === $fields) {
            throw ToolInput::invalid('settings', 'Send at least one setting to change.');
        }

        return $fields;
    }

    private static function website(ToolInput $input): string
    {
        $website = $input->string('website', 2048);
        if (!self::isUrl($website, ['http', 'https'])) {
            throw ToolInput::invalid('website', 'Enter a website address that starts with http:// or https://.');
        }

        return $website;
    }

    /** @param list<string> $schemes */
    private static function isUrl(string $url, array $schemes): bool
    {
        $parts = parse_url($url);

        return false !== filter_var($url, \FILTER_VALIDATE_URL)
            && \is_array($parts)
            && \in_array(strtolower((string) ($parts['scheme'] ?? '')), $schemes, true)
            && str_contains((string) ($parts['host'] ?? ''), '.');
    }
}
