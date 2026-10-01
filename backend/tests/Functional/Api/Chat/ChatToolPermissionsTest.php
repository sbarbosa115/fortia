<?php

namespace App\Tests\Functional\Api\Chat;

use App\Chat\Application\Tool\ChatTools;
use App\Chat\Domain\WriteQueue;
use App\Organizations\Application\Query\OrganizationQueries;
use App\Shared\Application\Security\Caller;
use App\Shared\Domain\Error\DomainError;
use App\Tests\Support\ApiTestCase;

/**
 * The chat's account tools check exactly what their HTTP endpoints check (PRD §7.19, §4.2, §8): the role
 * (AG or write permission) and ownership (another account's record is 404). A queued write is
 * checked when it is queued and again when it runs.
 */
final class ChatToolPermissionsTest extends ApiTestCase
{
    use ChatFixtures;

    private const WRITES_NEEDING_A_ROLE = [
        'create_organization' => ['name' => 'X'],
        'update_organization' => ['organization_id' => '11111111-1111-4111-8111-111111111111', 'name' => 'X'],
        'delete_organization' => ['organization_id' => '11111111-1111-4111-8111-111111111111'],
        'create_assignation' => ['organization_id' => '11111111-1111-4111-8111-111111111111', 'questionnaire_id' => '11111111-1111-4111-8111-111111111111', 'name' => 'A', 'type' => 'default'],
        'delete_assignation' => ['assignation_id' => '11111111-1111-4111-8111-111111111111'],
        'send_follow_up_reminder' => ['assignation_id' => '11111111-1111-4111-8111-111111111111'],
        'create_project' => ['organization_id' => '11111111-1111-4111-8111-111111111111', 'name' => 'P', 'due_date' => '2026-12-01'],
        'delete_project' => ['project_id' => '11111111-1111-4111-8111-111111111111'],
        'set_questionnaire_active' => ['questionnaire_id' => '11111111-1111-4111-8111-111111111111', 'is_active' => false],
        'copy_questionnaire' => ['questionnaire_id' => '11111111-1111-4111-8111-111111111111'],
        'update_account_language' => ['language' => 'en'],
        'update_account_settings' => ['max_files' => 3],
        'extract_brand_styles' => ['website' => 'https://acme.test'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->account('ACME0001');
    }

    public function testAReadOnlyUserCanQueueNoChange(): void
    {
        $reader = self::caller('ACME0001', ['Customer-Read-Only']);

        foreach (self::WRITES_NEEDING_A_ROLE as $tool => $input) {
            self::assertSame('FORBIDDEN', $this->refusal($reader, $tool, $input), "PRD §4.2: $tool needs the role its endpoint needs");
        }
    }

    public function testARootWithoutAnAdminGroupMayChangeWhatNeedsOnlyWritePermission(): void
    {
        $root = self::caller('ACME0001', [], root: true);
        $org = $this->data($this->api('POST', '/api/v1/organizations', ['name' => 'Acme Retail'], as: 'root@acme0001.test'), 201)['organization_id'];

        self::assertSame('FORBIDDEN', $this->refusal($root, 'create_organization', ['name' => 'X']), 'PRD §8.7: POST /organizations is AG');
        $queued = $this->tools()->call($root, 'update_organization', ['organization_id' => $org, 'description' => 'Stores'], WriteQueue::empty());
        self::assertSame('Acme Retail', $queued['result']['label'], 'PUT /organizations/{id} needs only write permission');
    }

    public function testAnotherAccountsRecordsAreNotFound(): void
    {
        $this->account('GLOBEX01');
        $org = $this->data($this->api('POST', '/api/v1/organizations', ['name' => 'Globex Org'], as: 'root@globex01.test'), 201)['organization_id'];
        $questionnaire = $this->ownQuestionnaire('root@globex01.test');
        $acme = self::caller('ACME0001', ['Customer-Admin'], root: true);

        self::assertSame('ORGANIZATION_NOT_FOUND', $this->refusal($acme, 'get_organization', ['organization_id' => $org]), 'another tenant\'s id is 404, never 403');
        self::assertSame('ORGANIZATION_NOT_FOUND', $this->refusal($acme, 'delete_organization', ['organization_id' => $org]));
        self::assertSame('QUESTIONNAIRE_NOT_FOUND', $this->refusal($acme, 'get_questionnaire', ['questionnaire_id' => $questionnaire]));
        self::assertSame('QUESTIONNAIRE_NOT_FOUND', $this->refusal($acme, 'copy_questionnaire', ['questionnaire_id' => $questionnaire]));
        self::assertSame('QUESTIONNAIRE_NOT_FOUND', $this->refusal($acme, 'list_questionnaire_answers', ['questionnaire_id' => $questionnaire]));
        self::assertSame('QUESTIONNAIRE_NOT_FOUND', $this->refusal($acme, 'get_questionnaire_analytics', ['questionnaire_id' => $questionnaire]));
        self::assertSame('ASSIGNATION_NOT_FOUND', $this->refusal($acme, 'list_assignation_respondents', ['assignation_id' => '11111111-1111-4111-8111-111111111111']));
        $rows = $this->tools()->call($acme, 'list_organizations', [], WriteQueue::empty())['result']['rows'];
        self::assertSame([], $rows, 'PRD §4.3: lists never show another account\'s rows');
    }

    public function testAQueuedWriteIsCheckedAgainWhenItRuns(): void
    {
        $admin = self::caller('ACME0001', ['Customer-Admin'], root: true);
        $queue = $this->tools()->call($admin, 'create_organization', ['name' => 'Later'], WriteQueue::empty())['queue'];

        $actions = $this->tools()->execute(self::caller('ACME0001', ['Customer-Read-Only']), $queue);

        self::assertSame('failed', $actions[0]['status']);
        self::assertSame('FORBIDDEN', $actions[0]['error']['code'], 'a tampered or stale queue can do no more than the user may');
        self::assertSame([], static::getContainer()->get(OrganizationQueries::class)->listFor('ACME0001'));
    }

    public function testInvalidInputIsAValidationErrorForTheModel(): void
    {
        $admin = self::caller('ACME0001', ['Customer-Admin'], root: true);

        self::assertSame('VALIDATION_ERROR', $this->refusal($admin, 'get_organization', ['organization_id' => 'nope']));
        self::assertSame('VALIDATION_ERROR', $this->refusal($admin, 'extract_brand_styles', ['website' => 'not a url']));
    }

    public function testInvitingUsersIsNotOffered(): void
    {
        $names = array_map(static fn ($t): string => $t->name, $this->tools()->definitions());

        self::assertNotContains('create_team_user', $names, 'PRD §7.19: deliberately excluded');
        self::assertNotContains('invite_user', $names);
        foreach (['list_plans', 'get_plan_and_usage', 'start_checkout', 'list_api_keys', 'list_webhooks', 'create_webhook'] as $removed) {
            self::assertNotContains($removed, $names, 'plans, billing and integrations were removed');
        }
        foreach (['list_questionnaires', 'get_questionnaire', 'list_questionnaire_answers', 'get_questionnaire_analytics', 'set_questionnaire_active', 'copy_questionnaire', 'get_profile', 'get_account_settings', 'update_account_language', 'update_account_settings', 'extract_brand_styles', 'list_team_users', 'list_videos', 'send_follow_up_reminder'] as $tool) {
            self::assertContains($tool, $names, "PRD §7.19 lists $tool");
        }
    }

    public function testTheQueueHoldsAtMostFiftyWrites(): void
    {
        $admin = self::caller('ACME0001', ['Customer-Admin'], root: true);
        $queue = WriteQueue::empty();
        for ($i = 0; $i < 50; ++$i) {
            $queue = $this->tools()->call($admin, 'update_account_language', ['language' => 'en'], $queue)['queue'];
        }

        try {
            $this->tools()->call($admin, 'update_account_language', ['language' => 'es'], $queue);
            self::fail('PRD §7.19: at most 50 queued writes');
        } catch (DomainError $e) {
            self::assertSame('WRITE_QUEUE_FULL', $e->errorCode());
        }
    }

    /** @param array<string, mixed> $input */
    private function refusal(Caller $caller, string $tool, array $input): ?string
    {
        try {
            $this->tools()->call($caller, $tool, $input, WriteQueue::empty());
        } catch (DomainError $e) {
            return $e->errorCode();
        }

        return null;
    }

    private function tools(): ChatTools
    {
        return static::getContainer()->get(ChatTools::class);
    }

    /** @param list<string> $groups */
    private static function caller(string $customerId, array $groups, bool $root = false): Caller
    {
        return new Caller('u-'.$customerId, 'root@'.strtolower($customerId).'.test', 'Test User', $customerId, $groups, $root);
    }
}
