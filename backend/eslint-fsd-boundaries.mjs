// Feature-Sliced Design import rules for the two apps (steps/04 §4.2), from the skill's
// templates/eslint-fsd-boundaries.mjs, adapted to one shared layer and two apps:
//
//   assets/shared/                       the shared layer of both apps (ui kit, api client, lib, config, i18n)
//   assets/{console,respondent}/app      providers, router, app shell
//   assets/{console,respondent}/pages/*  one slice per route
//   assets/{console,respondent}/widgets/*, features/*, entities/*
//
// Layers import only downwards; a slice never imports a sibling slice of its layer; code outside a slice imports
// it through its index.ts; and an app never imports the other app.
import boundaries from 'eslint-plugin-boundaries';

const SRC = 'assets';
const APPS = '(console|respondent)';
const LAYERS = ['app', 'pages', 'widgets', 'features', 'entities', 'shared'];
const SLICED = ['pages', 'widgets', 'features', 'entities'];
const below = (layer) => LAYERS.slice(LAYERS.indexOf(layer) + 1);
const sameApp = {app: '{{ from.element.captured.app }}'};

export default [
  {
    files: [`${SRC}/**/*.{ts,tsx}`],
    plugins: {boundaries},
    settings: {
      'import/resolver': {typescript: {alwaysTryTypes: true}, node: true},
      'boundaries/include': [`${SRC}/**/*`],
      'boundaries/ignore': [`${SRC}/types/**`],
      'boundaries/elements': [
        {type: 'app', pattern: `${SRC}/${APPS}/app`, capture: ['app']},
        ...SLICED.map((layer) => ({
          type: layer,
          pattern: `${SRC}/${APPS}/${layer}/*`,
          capture: ['app', 'slice'],
        })),
        {type: 'shared', pattern: `${SRC}/shared`},
      ],
    },
    rules: {
      'boundaries/dependencies': [
        'error',
        {
          default: 'disallow',
          message:
            'FSD: {{ from.element.types }} must not import this ({{ to.element.path }}). Layers import only downwards, a slice from outside only through its index.ts, and an app never imports the other app',
          policies: [
            // A layer may import the layers below it, in its own app (shared belongs to both).
            ...LAYERS.filter((l) => l !== 'shared').map((layer) => ({
              from: {element: {type: layer}},
              allow: [
                {to: {element: {type: 'shared'}}},
                {
                  to: {
                    element: {
                      types: {
                        anyOf: below(layer).filter((l) => l !== 'shared'),
                      },
                      captured: sameApp,
                    },
                  },
                },
              ],
            })),
            // Files of one slice import each other; app imports app; shared imports shared.
            ...SLICED.map((layer) => ({
              from: {element: {type: layer}},
              allow: {
                to: {
                  element: {
                    type: layer,
                    captured: {
                      app: '{{ from.element.captured.app }}',
                      slice: '{{ from.element.captured.slice }}',
                    },
                  },
                },
              },
            })),
            {
              from: {element: {type: 'app'}},
              allow: {to: {element: {type: 'app', captured: sameApp}}},
            },
            {
              from: {element: {type: 'shared'}},
              allow: {to: {element: {type: 'shared'}}},
            },
            // From outside a slice, only its public API. Last, so it wins over the allows above.
            {
              disallow: {
                to: {
                  element: {
                    types: {anyOf: SLICED},
                    fileInternalPath: '!index.{ts,tsx}',
                  },
                },
              },
            },
          ],
        },
      ],
    },
  },
];
