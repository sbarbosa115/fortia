import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';
import globals from 'globals';
import tseslint from 'typescript-eslint';
import fsd from './eslint-fsd-boundaries.mjs';

// Prettier owns layout; ESLint owns correctness and the non-layout rules of the Google JavaScript Style Guide
// (steps/05 §5.2). eslint-config-prettier goes last so no rule fights Prettier.
export default tseslint.config(
  {
    ignores: [
      'public/**',
      'vendor/**',
      'var/**',
      'node_modules/**',
      'assets/types/**',
      '*.config.js',
      'webpack.config.js',
    ],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  {
    files: ['assets/**/*.{ts,tsx}'],
    plugins: {'react': react, 'react-hooks': reactHooks},
    languageOptions: {globals: {...globals.browser}},
    settings: {react: {version: 'detect'}},
    rules: {
      ...react.configs.recommended.rules,
      ...react.configs['jsx-runtime'].rules,
      ...reactHooks.configs.recommended.rules,
      // A missing import is a blank screen, not a build error (steps/04 §4.2).
      'react/jsx-no-undef': 'error',
      'react/prop-types': 'off',
      // Every visible string goes through i18n (steps/04 §4.2): literal text in JSX is refused.
      'react/jsx-no-literals': [
        'error',
        {
          noStrings: false,
          ignoreProps: true,
          allowedStrings: [
            '·',
            '—',
            '–',
            '/',
            '%',
            '×',
            '•',
            ':',
            '(',
            ')',
            '+',
            '*',
            '…',
            '#',
          ],
        },
      ],
    },
  },
  {
    files: ['**/*.test.{ts,tsx}'],
    rules: {'react/jsx-no-literals': 'off'},
  },
  {
    files: ['**/*.{js,jsx,ts,tsx,mjs,mts}'],
    rules: {
      'no-var': 'error',
      'prefer-const': 'error',
      'prefer-rest-params': 'error',
      'prefer-spread': 'error',
      'eqeqeq': ['error', 'always', {null: 'ignore'}],
      'new-cap': ['error', {capIsNew: false}],
      'no-throw-literal': 'error',
      'guard-for-in': 'error',
    },
  },
  {
    files: ['**/*.{ts,tsx,mts}'],
    rules: {
      '@typescript-eslint/naming-convention': [
        'error',
        {
          selector: 'default',
          format: ['camelCase'],
          leadingUnderscore: 'allow',
        },
        {
          selector: 'variable',
          format: ['camelCase', 'UPPER_CASE', 'PascalCase'],
          leadingUnderscore: 'allow',
        },
        {selector: 'function', format: ['camelCase', 'PascalCase']},
        {selector: 'typeLike', format: ['PascalCase']},
        {selector: 'enumMember', format: ['PascalCase', 'UPPER_CASE']},
        {
          selector: ['property', 'parameterProperty', 'objectLiteralMethod'],
          format: null,
        },
        {selector: 'import', format: null},
      ],
      '@typescript-eslint/no-unused-vars': [
        'error',
        {argsIgnorePattern: '^_', varsIgnorePattern: '^_'},
      ],
    },
  },
  ...fsd,
  prettier,
);
