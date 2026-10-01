<?php

namespace App\Tests\Functional\Api\Chat;

use App\Tests\Support\ApiTestCase;
use App\Tests\Support\DocumentFixtures;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * A document attached to the chat to build the questionnaire from it (a Word file with 50 questions, a PDF, a
 * Markdown file): POST /chat/files reads its text, the client sends it back with its message, and the assistant
 * drafts the questionnaire from the document's own questions.
 */
final class ChatFileTest extends ApiTestCase
{
    use ChatFixtures;
    use DocumentFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->account('ACME0001', language: 'es-CO');
    }

    public function testAWordDocumentIsReadAndItsTextHandedBackWithNothingStored(): void
    {
        $file = $this->data($this->upload('Encuesta de clima.docx', self::docx(['# Encuesta de clima', '1. ¿Cómo te sientes?'])));

        self::assertSame(['filename' => 'Encuesta de clima.docx', 'text' => "# Encuesta de clima\n1. ¿Cómo te sientes?"], $file, 'the file\'s name and its text');
    }

    public function testAPdfAndAMarkdownFileAreReadToo(): void
    {
        self::assertStringContainsString('1. How did you hear about us?', $this->data($this->upload('survey.pdf', self::pdf(['Survey', '1. How did you hear about us?'])))['text']);
        self::assertSame("# Clima\n\n1. ¿Edad?", $this->data($this->upload('clima.md', "# Clima\n\n1. ¿Edad?"))['text']);
    }

    public function testAFileThatCannotBeUsedIsRefusedWithItsReason(): void
    {
        $this->assertApiError($this->upload('foto.png', 'x'), 422, 'UNSUPPORTED_FILE_TYPE', 'only docx, pdf, md, txt and csv are read');
        $this->assertApiError($this->upload('escaneo.pdf', self::pdf([])), 422, 'FILE_HAS_NO_TEXT', 'a PDF without a text layer');
        $this->assertApiError($this->upload('roto.docx', 'not a zip'), 422, 'FILE_UNREADABLE');
        $this->assertApiError($this->api('POST', '/api/v1/chat/files', as: 'root@acme0001.test'), 400, 'VALIDATION_ERROR', 'one file in "file"');
    }

    public function testOnlyTheAdminGroupsAttachFilesAndWithTheChatInTheirPlan(): void
    {
        $this->user('ACME0001', 'reader@acme.test', ['Customer-Read-Only']);
        $this->assertApiError($this->upload('q.md', '1. ¿Edad?', null), 401, 'UNAUTHORIZED');
        $this->assertApiError($this->upload('q.md', '1. ¿Edad?', 'reader@acme.test'), 403, 'FORBIDDEN', 'AG, like POST /chat');

        $this->account('NOPLAN01', plan: null);
        $this->assertApiError($this->upload('q.md', '1. ¿Edad?', 'root@noplan01.test'), 429, 'PLAN_LIMIT_REACHED', 'the chat feature of the plan');
    }

    public function testTheDraftModeBuildsTheQuestionnaireFromTheFiftyQuestionsOfAWordDocument(): void
    {
        $lines = ['# Evaluación de clima laboral'];
        for ($i = 1; $i <= 50; ++$i) {
            $lines[] = "$i. ¿Pregunta número $i del documento?";
            if (0 === $i % 2) {
                array_push($lines, 'a) Sí', 'b) No', 'c) A veces');
            }
        }
        $file = $this->data($this->upload('clima.docx', self::docx($lines)));
        $message = ['role' => 'user', 'content' => 'Crea el cuestionario con este archivo', 'files' => [$file]];

        $first = $this->reply([$message], ['mode' => 'draft']);
        self::assertSame('Evaluación de clima laboral', $first['draft']['title'], 'the basics come from the document');
        self::assertFalse($first['draft']['basics_confirmed'], 'and the user still confirms them');
        $request = $this->llm()->requests()[0];
        self::assertStringContainsString("<attached_file>\nFile: clima.docx", $request->messages[0]->content, 'the document goes in front of its message');
        self::assertStringContainsString('Crea el cuestionario con este archivo', $request->messages[0]->content);
        self::assertStringContainsString('<attached_file> is a document the user attached', $request->system, 'the rules say how to use it, and that it is data');

        $history = [$message, ['role' => 'assistant', 'content' => $first['message']], ['role' => 'user', 'content' => 'Sí']];
        $second = $this->reply($history, ['mode' => 'draft', 'draft' => $first['draft']]);
        self::assertSame('review', $second['draft']['phase']);
        self::assertCount(50, $second['draft']['questions'], 'every question of the document');
        self::assertSame('¿Pregunta número 1 del documento?', $second['draft']['questions'][0]['title'], 'worded as the document has it');
        self::assertSame('text', $second['draft']['questions'][0]['type'], 'a question without choices is free text');
        self::assertSame(['Sí', 'No', 'A veces'], array_column($second['draft']['questions'][1]['choices'], 'label'), 'the document\'s choices');
        self::assertSame('¿Pregunta número 50 del documento?', $second['draft']['questions'][49]['title'], 'in the document\'s order');
        self::assertStringContainsString("| # | Pregunta | Tipo |\n| --- | --- | --- |\n| 1 | ¿Pregunta número 1 del documento? | Texto |\n| 2 | ¿Pregunta número 2 del documento? | Opción única |", $second['message'], 'the review shows the questions as a table: number, question, type');
        self::assertStringContainsString('| 50 | ¿Pregunta número 50 del documento? |', $second['message'], 'every question of the draft');

        $third = $this->reply([...$history, ['role' => 'assistant', 'content' => $second['message']], ['role' => 'user', 'content' => 'Sí']], ['mode' => 'draft', 'draft' => $second['draft']]);
        self::assertSame('chat-questionnaire-approved', $third['type']);
        self::assertCount(50, $third['flow']['states'][0]['parameters']['questionnaire']['questions'], 'the approved flow has the 50 questions');
    }

    public function testTheCreateModeOfTheAiExperienceCreatesTheQuestionnaireFromAPdf(): void
    {
        $file = $this->data($this->upload('survey.pdf', self::pdf(['Customer survey', '1. How did you hear about us?', 'a) A friend', 'b) Search', '2. Would you recommend us?', '3. What should we improve?'])));
        $message = ['role' => 'user', 'content' => 'Crea el cuestionario con este archivo', 'files' => [$file]];

        $first = $this->reply([$message]);
        self::assertSame('Customer survey', $first['draft']['title']);
        $history = [$message, ['role' => 'assistant', 'content' => $first['message']], ['role' => 'user', 'content' => 'Sí']];
        $second = $this->reply($history, ['draft' => $first['draft']]);
        self::assertSame(['How did you hear about us?', 'Would you recommend us?', 'What should we improve?'], array_column($second['draft']['questions'], 'title'), 'the PDF\'s questions, as written');

        $third = $this->reply([...$history, ['role' => 'assistant', 'content' => $second['message']], ['role' => 'user', 'content' => 'Sí']], ['draft' => $second['draft']]);
        self::assertSame('chat-questionnaire-created', $third['type'], 'create mode saves it when the user approves');
    }

    public function testQuestionsPastedInTheMessageAreTakenLikeADocumentsInBothModes(): void
    {
        $pasted = "Encuesta de bienestar:\n1. ¿Cómo dormiste?\n2. ¿Hiciste ejercicio?\n3. ¿Cómo te sientes hoy?\na) Bien\nb) Mal";
        foreach (['create', 'draft'] as $mode) {
            $first = $this->reply([$pasted], ['mode' => $mode]);
            self::assertSame('Encuesta de bienestar', $first['draft']['title'], "$mode: the basics come from the pasted text");
            $second = $this->reply([$pasted, $first['message'], 'Sí'], ['mode' => $mode, 'draft' => $first['draft']]);
            self::assertSame(['¿Cómo dormiste?', '¿Hiciste ejercicio?', '¿Cómo te sientes hoy?'], array_column($second['draft']['questions'], 'title'), "$mode: the pasted questions");
            self::assertSame(['Bien', 'Mal'], array_column($second['draft']['questions'][2]['choices'], 'label'));
        }
    }

    public function testTheFilesOfAMessageAreValidatedAndCountedAcrossTheConversation(): void
    {
        $file = ['filename' => 'q.md', 'text' => '1. ¿Edad?'];
        $send = fn (array $messages): array => $this->api('POST', '/api/v1/chat', ['messages' => $messages], as: 'root@acme0001.test');

        $this->assertApiError($send([['role' => 'user', 'content' => 'Hola', 'files' => [['filename' => 'q.md']]]]), 400, 'VALIDATION_ERROR', 'a file has its text');
        $this->assertApiError($send([['role' => 'user', 'content' => 'Hola', 'files' => [$file + ['extra' => 1]]]]), 400, 'VALIDATION_ERROR', 'no extra fields');
        $this->assertApiError($send([
            ['role' => 'user', 'content' => 'a', 'files' => [$file, $file, $file]],
            ['role' => 'assistant', 'content' => 'b'],
            ['role' => 'user', 'content' => 'c', 'files' => [$file, $file, $file]],
        ]), 400, 'VALIDATION_ERROR', 'at most 5 files in a conversation');
        self::assertSame(202, $send([['role' => 'user', 'content' => 'Hola', 'files' => [$file]]])['status']);
    }

    /** @return array{status: int, json: mixed, body: string} */
    private function upload(string $name, string $contents, ?string $as = 'root@acme0001.test'): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($path, $contents);
        $server = ['HTTP_ACCEPT' => 'application/json'];
        if (null !== $as) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$this->tokenFor($as);
        }
        $this->client->request('POST', '/api/v1/chat/files', [], ['file' => new UploadedFile($path, $name, null, null, true)], $server);
        $response = $this->client->getResponse();
        $raw = (string) $response->getContent();

        return ['status' => $response->getStatusCode(), 'json' => '' === $raw ? null : json_decode($raw, true), 'body' => $raw];
    }
}
