<?php

namespace App\Tests\Unit\Chat;

use App\Chat\Infrastructure\Llm\DocumentQuestions;
use PHPUnit\Framework\TestCase;

final class DocumentQuestionsTest extends TestCase
{
    /** A Word questionnaire as FileDocumentText reads it: automatic numbering lost, sections, tables and boxes. */
    private const WORD = <<<'TXT'
        # Cuestionario de dimensionamiento
        # Módulo de personal

        CONSIDERACIONES:

        - Se recomienda que este documento sea llenado en conjunto entre el líder del proyecto y los involucrados.

        - Es necesario indicar todos los usuarios requeridos para este módulo.

        - Usuario con todos los permisos
        - Usuario con accesos limitados

        # PLANTILLAS DE PERSONAL

        - ¿Requiere contar con una estructura organizacional controlada a través de una plantilla?

        Si | No

        - Del siguiente listado por favor selecciona las opciones que mejor se adecuen. Se puede seleccionar más de una

        Razón social | Departamento
        Ubicación base | Centro de costos

        Ejemplo:

        - ¿Cómo quieres visualizar el nombre de tus plazas?

        Se usará nombre
        Se usará las claves

        - En caso de que su respuesta sea afirmativa favor de indicar quien sería la persona responsable.

        # MODIFICACIONES

        Esta sección será informativa, únicamente es para conocer quiénes son los responsables de cada proceso.

        - ¿Quién podrá generar las altas?

        Nombre colaborador | Clave colaborador | Puesto | Observación
        | | |
        | | |

        # PERFIL DE PUESTOS
        - En caso de que la respuesta anterior sea afirmativa indícanos que secciones manejas en tus perfiles
        Concepto | Descripción | Requerido Si / No
        Objetivo | Objetivo general de la posición
        Experiencia | Condiciones de trabajo que debe tener el colaborador | ☐
        Idiomas | Idioma y nivel requerido para la posición

        - Como manejas las vacaciones adelantadas (favor de solo seleccionar una opción):

        Aplica | Tipo de vacaciones adelantadas
        Proporcional a la antigüedad.
        Un porcentaje de las vacaciones del siguiente año
        | Indique el porcentaje:

        # Avisos de alta al sistema

        Clave de colaborador | Correo del colaborador
        |
        |

        # Cumpleaños

        ¿A quién le llega el Aviso? | Indique
        - Destinatario Alternativo (solo una persona) |
        - No se Utilizará esta notificación

        # SEGUROS.

        - ¿Requiere contar con información de seguros ofrecidos a sus colaboradores?

        Catalogo | Requerido Si / No
        Seguro de Vida | ☐
        Seguro de GMM
        TXT;

    public function testItTakesEveryQuestionOfAWordDocumentWhoseNumbersWereLostWithTheirChoicesAndTables(): void
    {
        $questions = DocumentQuestions::in(self::WORD);

        self::assertSame([
            '¿Requiere contar con una estructura organizacional controlada a través de una plantilla?',
            'Del siguiente listado por favor selecciona las opciones que mejor se adecuen. Se puede seleccionar más de una',
            '¿Cómo quieres visualizar el nombre de tus plazas?',
            'En caso de que su respuesta sea afirmativa favor de indicar quien sería la persona responsable.',
            '¿Quién podrá generar las altas?',
            'En caso de que la respuesta anterior sea afirmativa indícanos que secciones manejas en tus perfiles',
            'Como manejas las vacaciones adelantadas (favor de solo seleccionar una opción):',
            'Avisos de alta al sistema',
            'Cumpleaños: ¿A quién le llega el Aviso?',
            '¿Requiere contar con información de seguros ofrecidos a sus colaboradores?',
        ], array_column($questions, 'title'), 'every question, worded as it is, in order; the considerations before the first section of questions are not questions');

        self::assertSame(['radio', 'checkbox', 'radio', 'text', 'table', 'checkbox', 'radio', 'table', 'radio', 'checkbox'], array_column($questions, 'type'));
        self::assertSame(['Si', 'No'], self::labels($questions[0]), 'a row of short cells under the question is its choices');
        self::assertSame(['Razón social', 'Departamento', 'Ubicación base', 'Centro de costos'], self::labels($questions[1]), 'a grid of options; "more than one" makes it a checkbox');
        self::assertSame(['Se usará nombre', 'Se usará las claves'], self::labels($questions[2]), 'short plain lines under the question');
        self::assertSame(['Nombre colaborador', 'Clave colaborador', 'Puesto', 'Observación'], $questions[4]['columns'], 'a header and empty rows to fill in is a table');
        self::assertSame(['Objetivo', 'Experiencia', 'Idiomas'], self::labels($questions[5]), 'a table whose rows name the options');
        self::assertSame(['Proporcional a la antigüedad', 'Un porcentaje de las vacaciones del siguiente año'], self::labels($questions[6]), '"only one" makes it a radio');
        self::assertSame(['Clave de colaborador', 'Correo del colaborador'], $questions[7]['columns'], 'a table to fill in under a heading is a question titled by it');
        self::assertSame(['Destinatario Alternativo (solo una persona)', 'No se Utilizará esta notificación'], self::labels($questions[8]));
        self::assertSame(['Seguro de Vida', 'Seguro de GMM'], self::labels($questions[9]), 'rows with a box to tick are options');
    }

    public function testItReadsNumberedQuestionsWithLetteredAndBoxedChoices(): void
    {
        $questions = DocumentQuestions::in("Encuesta\n**1.** ¿Cómo dormiste?\na) Bien\nb) Mal\n2. ¿Manejas vacaciones adelantadas? ☐ Si ☐ No\n3) Comentarios\n\n1. PLANTILLAS\n1.8.2 Avisos de baja");

        self::assertSame(['¿Cómo dormiste?', '¿Manejas vacaciones adelantadas?', 'Comentarios'], array_column($questions, 'title'), 'numbered capitals and multi-level numbers are headings');
        self::assertSame(['Bien', 'Mal'], self::labels($questions[0]));
        self::assertSame(['Si', 'No'], self::labels($questions[1]), 'boxes on the question\'s own line');
        self::assertSame('text', $questions[2]['type'], 'a question without choices is free text');
    }

    /**
     * @param array<string, mixed> $question
     *
     * @return list<string>
     */
    private static function labels(array $question): array
    {
        return array_column((array) ($question['choices'] ?? []), 'label');
    }
}
