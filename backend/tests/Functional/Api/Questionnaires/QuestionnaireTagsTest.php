<?php

namespace App\Tests\Functional\Api\Questionnaires;

use App\Tests\Support\ApiTestCase;

/**
 * A questionnaire's tags through POST/PUT /questionnaire, GET /questionnaire/{id}, the listing and the copy: free-text
 * labels, trimmed and deduplicated, at most 20 of at most 40 characters.
 */
final class QuestionnaireTagsTest extends ApiTestCase
{
    use FlowPayloads;

    public function testAQuestionnaireIsCreatedWithItsTagsNormalized(): void
    {
        $owner = $this->account('ACME0001');

        $id = $this->createQuestionnaire($owner, ['tags' => [' AP-03 ', 'NP-12', 'ap-03', '', 'Some']] + self::regularFlow());

        $questionnaire = $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $owner));
        self::assertSame(['AP-03', 'NP-12', 'Some'], $questionnaire['tags'], 'tags are trimmed, empties and repeats (any case) dropped, the first spelling kept');
    }

    public function testAQuestionnaireWithoutTagsHasAnEmptyList(): void
    {
        $owner = $this->account('ACME0001');

        $id = $this->createQuestionnaire($owner, self::regularFlow());

        self::assertSame([], $this->tagsOf($id, $owner));
    }

    public function testAnEditWithoutTagsKeepsThemAndAnEditWithTagsReplacesThem(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, ['tags' => ['AP-03', 'NP-12']] + self::regularFlow());

        $keep = $this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id] + self::regularFlow('Renamed'), as: $owner);
        self::assertSame(200, $keep['status'], $keep['body']);
        self::assertSame(['AP-03', 'NP-12'], $this->tagsOf($id, $owner), 'tags omitted on PUT: the questionnaire keeps them');

        $replace = $this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id, 'tags' => ['Some']] + self::regularFlow('Renamed'), as: $owner);
        self::assertSame(200, $replace['status'], $replace['body']);
        self::assertSame(['Some'], $this->tagsOf($id, $owner), 'tags sent on PUT replace the list');

        $this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id, 'tags' => []] + self::regularFlow('Renamed'), as: $owner);
        self::assertSame([], $this->tagsOf($id, $owner), 'an empty list clears them');
    }

    public function testTooManyOrTooLongTagsAreAValidationError(): void
    {
        $owner = $this->account('ACME0001');
        $tooMany = array_map(static fn (int $i): string => 'T'.$i, range(1, 21));

        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', ['tags' => $tooMany] + self::regularFlow(), as: $owner), 400, 'VALIDATION_ERROR', 'at most 20 tags');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', ['tags' => [str_repeat('a', 41)]] + self::regularFlow(), as: $owner), 400, 'VALIDATION_ERROR', 'at most 40 characters a tag');
        $this->assertApiError($this->api('POST', '/api/v1/questionnaire', ['tags' => [3]] + self::regularFlow(), as: $owner), 400, 'VALIDATION_ERROR', 'tags are texts');
        $id = $this->createQuestionnaire($owner, ['tags' => ['Keep']] + self::regularFlow());
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id, 'tags' => $tooMany] + self::regularFlow(), as: $owner), 400, 'VALIDATION_ERROR');
        self::assertSame(['Keep'], $this->tagsOf($id, $owner), 'a refused edit changes nothing');
    }

    public function testTheListingAndTheActiveToggleReturnTheTags(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, ['tags' => ['AP-03']] + self::regularFlow('Tagged'));

        $items = $this->data($this->api('GET', '/api/v1/questionnaire', as: $owner))['items'];
        self::assertSame(['AP-03'], $items[0]['tags'], 'each listed questionnaire carries its tags');

        $row = $this->data($this->api('PATCH', "/api/v1/questionnaire/$id", ['is_active' => false], as: $owner));
        self::assertSame(['AP-03'], $row['tags'], 'PATCH answers the row with its tags, untouched');
    }

    public function testACopyKeepsTheTags(): void
    {
        $owner = $this->account('ACME0001');
        $id = $this->createQuestionnaire($owner, ['tags' => ['AP-03', 'NP-12']] + self::regularFlow('Survey', 'survey'));

        $copy = $this->data($this->api('POST', "/api/v1/questionnaire/$id/copy", as: $owner), 201);

        self::assertSame(['AP-03', 'NP-12'], $copy['tags']);
    }

    public function testAnotherTenantCannotReadOrRetagTheQuestionnaire(): void
    {
        $owner = $this->account('ACME0001');
        $globex = $this->account('GLOBEX01');
        $id = $this->createQuestionnaire($owner, ['tags' => ['AP-03']] + self::regularFlow());

        $this->assertApiError($this->api('GET', "/api/v1/questionnaire/$id", as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND', 'another tenant\'s id is 404, never 403');
        $this->assertApiError($this->api('PUT', '/api/v1/questionnaire', ['questionnaire_id' => $id, 'tags' => ['Theirs']] + self::regularFlow(), as: $globex), 404, 'QUESTIONNAIRE_NOT_FOUND');
        self::assertSame(['AP-03'], $this->tagsOf($id, $owner), 'the other tenant changed nothing');
        self::assertSame([], $this->data($this->api('GET', '/api/v1/questionnaire', as: $globex))['items'], 'nor sees it listed');
    }

    /** @return list<string> */
    private function tagsOf(string $id, string $as): array
    {
        return $this->data($this->api('GET', "/api/v1/questionnaire/$id", as: $as))['tags'];
    }
}
