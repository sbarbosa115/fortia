import type {components} from '@api-types/api';

/**
 * A response or request shape of the API, from the generated OpenAPI types (never hand-written):
 *
 *     type Questionnaire = Schema<'QuestionnaireOutput'>;
 *
 * After changing a controller or a DTO, regenerate: bin/console nelmio:apidoc:dump --format=json >
 * assets/types/openapi.json && npm run -s api:types.
 */
export type Schema<K extends keyof components['schemas']> =
  components['schemas'][K];

/** The success envelope of PRD §8.1. */
export type Envelope<T> = {message: string; data: T};
