import type {FollowUpAnswer} from '@console/entities/assignation';
import {testI18n} from '@shared/i18n/testing';
import {render, screen, within} from '@testing-library/react';
import type {ReactNode} from 'react';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it, vi} from 'vitest';
import {AnswersTable} from './AnswersTable';
import {ReviewDialog} from './ReviewDialog';

function tableAnswer(): FollowUpAnswer {
  return {
    question_id: 'q1',
    position: 1,
    title: 'Who joins and leaves?',
    type: 'table',
    answer: 'Altas — Nombre: Ana; Correo: ana@acme.test\nBajas — Nombre: Luis',
    answer_table: {
      columns: [
        {key: 'nombre', label: 'Nombre'},
        {key: 'correo', label: 'Correo'},
      ],
      rows: [
        {label: 'Altas', cells: {nombre: 'Ana', correo: 'ana@acme.test'}},
        {label: 'Bajas', cells: {nombre: 'Luis', correo: ''}},
      ],
    },
    skipped: false,
    answered_at: '2026-09-29T10:00:00Z',
    locked: false,
    review: null,
    review_state: 'not_reviewed',
  };
}

function textAnswer(): FollowUpAnswer {
  return {
    ...tableAnswer(),
    question_id: 'q2',
    position: 2,
    title: 'Anything else?',
    type: 'text',
    answer: 'Nothing',
    answer_table: null,
  };
}

function renderIn(node: ReactNode) {
  return render(
    <I18nextProvider i18n={testI18n('console')}>{node}</I18nextProvider>,
  );
}

function expectTheTable(table: HTMLElement) {
  expect(
    within(table).getByRole('columnheader', {name: 'Nombre'}),
  ).toBeInTheDocument();
  expect(
    within(table).getByRole('columnheader', {name: 'Correo'}),
  ).toBeInTheDocument();
  expect(
    within(table).getByRole('rowheader', {name: 'Altas'}),
  ).toBeInTheDocument();
  expect(
    within(table).getByRole('cell', {name: 'ana@acme.test'}),
  ).toBeInTheDocument();
  expect(within(table).getByRole('cell', {name: 'Luis'})).toBeInTheDocument();
}

describe('a table answer in the follow-up', () => {
  it('is previewed as a table in the answers table, with its headers and cells', () => {
    renderIn(
      <AnswersTable
        answers={[tableAnswer(), textAnswer()]}
        attempt={1}
        onView={vi.fn()}
      />,
    );

    expectTheTable(screen.getByRole('table', {name: 'Who joins and leaves?'}));
    expect(screen.queryByText(/Nombre: Ana/)).not.toBeInTheDocument();
    expect(screen.getByText('Nothing')).toBeInTheDocument();
  });

  it('is shown as a table in the review dialog', () => {
    renderIn(
      <ReviewDialog
        answers={[tableAnswer()]}
        index={0}
        canReview
        finished={false}
        saving={false}
        onMove={vi.fn()}
        onDecide={vi.fn()}
        onClose={vi.fn()}
      />,
    );

    const dialog = screen.getByRole('dialog');
    expectTheTable(
      within(dialog).getByRole('table', {name: 'Who joins and leaves?'}),
    );
    expect(
      within(dialog).getByRole('region', {name: 'Who joins and leaves?'}),
    ).toHaveAttribute('tabindex', '0');
  });
});
