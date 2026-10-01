<?php

namespace App\Tests\Functional\Api\Branding;

use App\Branding\Domain\Model\CustomerStyles;
use App\Tests\Support\ApiTestCase;

/** PRD §8.5 GET /styles?customer_id=&questionnaire_id= · P: the brand the respondent app applies (§9.15). */
final class StylesReadTest extends ApiTestCase
{
    public function testAnAccountWithoutStylesReadsNull(): void
    {
        $this->account('ACME0001');

        $data = $this->data($this->api('GET', '/api/v1/styles?customer_id=ACME0001'));

        self::assertSame(['styles' => null], $data, '§8.5 {styles | null}: the respondent app keeps its default theme');
    }

    public function testAnAccountsStylesArePublic(): void
    {
        $this->account('ACME0001');
        $styles = ['logoUrl' => 'https://acme.test/logo.png', 'body' => ['background' => '#ffffff', 'color' => '#111111']];
        $this->em()->persist(new CustomerStyles('ACME0001', 'https://acme.test', $styles, new \DateTimeImmutable()));
        $this->em()->flush();

        $data = $this->data($this->api('GET', '/api/v1/styles?customer_id=ACME0001&questionnaire_id=ignored'));

        self::assertSame($styles, $data['styles'], '§8.5 only customer_id is used');
    }

    public function testCustomerIdOrQuestionnaireIdIsRequired(): void
    {
        $this->assertApiError($this->api('GET', '/api/v1/styles'), 400, 'INVALID_REQUEST', '§8.5: 400 if both are missing');
        self::assertSame(['styles' => null], $this->data($this->api('GET', '/api/v1/styles?questionnaire_id=x')), 'only customer_id is used');
    }
}
