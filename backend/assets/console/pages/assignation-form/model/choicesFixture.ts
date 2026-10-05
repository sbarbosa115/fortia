import type {ChoiceRow} from './questionnaireFilter';

/** 30 tags, some with accents, to try step 1 with a realistic account (tests only). */
export const FIXTURE_TAGS = [
  'MatchCode',
  'RRHH',
  'Ventas',
  'Onboarding',
  'Legal',
  'Q3',
  'Q4',
  'Clima laboral',
  'Liderazgo',
  'Finanzas',
  'Logística',
  'Compras',
  'Calidad',
  'Seguridad',
  'TI',
  'Marketing',
  'Atención al cliente',
  'Operaciones',
  'Auditoría',
  'Proveedores',
  'Inventario',
  'Formación',
  'Cumplimiento',
  'Riesgos',
  'Innovación',
  'Producto',
  'Soporte',
  'Expansión',
  'Diagnóstico',
  'Satisfacción',
];

const SUBJECTS = [
  'Auditoría de tienda',
  'Encuesta de clima',
  'Diagnóstico de procesos',
  'Evaluación de proveedores',
  'Plantilla de personal',
  'Feedback de producto',
  'Revisión de inventario',
  'Mapeo de compras',
  'Chequeo de seguridad',
  'Plan de formación',
  'Control de calidad',
  'Satisfacción del cliente',
];
const COMPANIES = ['Empresa X', 'Empresa Y', 'Empresa Z', 'Acme', 'Globex'];

/**
 * 57 questionnaires over the 30 tags: tag i is on a decreasing number of them (the first ones are the most used),
 * the 1st has no questions, every 7th is inactive and every 10th has no tags.
 */
export function fixtureChoices(count = 57): ChoiceRow[] {
  return Array.from({length: count}, (_, index) => {
    const tags =
      index % 10 === 9
        ? []
        : [
            FIXTURE_TAGS[index % 6],
            FIXTURE_TAGS[6 + ((index * 7) % 24)],
            ...(index % 3 === 0 ? [FIXTURE_TAGS[(index * 5) % 30]] : []),
          ].filter((tag, at, all) => all.indexOf(tag) === at);
    return {
      questionnaire_id: `q-${String(index + 1).padStart(2, '0')}`,
      title: `${SUBJECTS[index % SUBJECTS.length]} — ${COMPANIES[index % COMPANIES.length]} ${index + 1}`,
      type: 'default',
      question_count: index === 0 ? 0 : 1 + ((index * 13) % 45),
      is_active: index % 7 !== 6,
      tags,
      updated_at: new Date(Date.UTC(2026, 0, 1 + index)).toISOString(),
      created_at: new Date(Date.UTC(2025, 0, 1 + index)).toISOString(),
    } as ChoiceRow;
  });
}
