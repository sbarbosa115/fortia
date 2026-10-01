import {
  type AnswerValue,
  type Control,
  makeControl,
  makeQuestion,
  type Question,
} from '@respondent/entities/session';
import {testI18n} from '@shared/i18n/testing';
import {fireEvent, render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it, vi} from 'vitest';
import {AnswerControl} from './AnswerControl';

const options = (...values: string[]) =>
  values.map((value) => ({label: value.toUpperCase(), value, visibility: []}));

function Harness({
  control,
  question,
  onValue,
  onSubmit,
}: {
  control: Control;
  question?: Partial<Question>;
  onValue?: (value: AnswerValue) => void;
  onSubmit?: () => void;
}) {
  const [value, setValue] = useState<AnswerValue>(control.value);
  const current = {...control, value};
  return (
    <AnswerControl
      question={makeQuestion('q', [current], {
        title: 'The question',
        ...question,
      })}
      control={current}
      gender="male"
      disabled={false}
      onChange={(next) => {
        setValue(next);
        onValue?.(next);
      }}
      onChangeControl={() => undefined}
      onSubmit={onSubmit}
    />
  );
}

function renderControl(props: Parameters<typeof Harness>[0]) {
  return render(
    <I18nextProvider i18n={testI18n('respondent')}>
      <Harness {...props} />
    </I18nextProvider>,
  );
}

describe('AnswerControl (PRD §9.4)', () => {
  it('selects a radio card with its letter', async () => {
    const onValue = vi.fn();
    renderControl({
      control: makeControl({type: 'radio', options: options('good', 'bad')}),
      onValue,
    });
    expect(screen.getByText('A')).toBeInTheDocument();
    await userEvent.click(screen.getByRole('radio', {name: /BAD/}));
    expect(onValue).toHaveBeenLastCalledWith('bad');
    expect(screen.getByRole('radio', {name: /BAD/})).toBeChecked();
  });

  it('keeps an exclusive checkbox alone', async () => {
    const onValue = vi.fn();
    renderControl({
      control: makeControl({
        type: 'checkbox',
        options: options('email', 'chat', 'none'),
      }),
      onValue,
    });
    await userEvent.click(screen.getByRole('checkbox', {name: /EMAIL/}));
    await userEvent.click(screen.getByRole('checkbox', {name: /CHAT/}));
    await userEvent.click(screen.getByRole('checkbox', {name: /NONE/}));
    expect(onValue).toHaveBeenLastCalledWith(['none']);
    expect(screen.getByRole('checkbox', {name: /EMAIL/})).not.toBeChecked();
  });

  it('offers "Select an option" in a select', () => {
    renderControl({
      control: makeControl({type: 'select', options: options('co', 'mx')}),
    });
    expect(screen.getByRole('combobox')).toHaveDisplayValue('Select an option');
  });

  it('asks to move the slider until it is touched, and starts at default_value', () => {
    const onValue = vi.fn();
    renderControl({
      control: makeControl({type: 'range', default_value: 7}),
      onValue,
    });
    expect(
      screen.getByText('Move or tap the slider to answer'),
      'the starting position does not count as an answer',
    ).toBeInTheDocument();
    const slider = screen.getByRole('slider');
    expect(slider).toHaveValue('7');
    fireEvent.change(slider, {target: {value: '8'}});
    expect(onValue).toHaveBeenLastCalledWith('8');
    expect(screen.queryByText('Move or tap the slider to answer')).toBeNull();
  });

  it('shows the format rule of a text answer and submits on Enter', async () => {
    const onSubmit = vi.fn();
    renderControl({
      control: makeControl({
        type: 'text',
        validations: [
          {type: 'format', value: 'letters', message: null, pattern: null},
        ],
      }),
      onSubmit,
    });
    const area = screen.getByRole('textbox', {name: 'The question'});
    expect(area).toHaveAttribute('placeholder', 'Type your answer here...');
    await userEvent.type(area, 'abc1');
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Only letters are allowed',
    );
    await userEvent.type(area, '{Enter}');
    expect(onSubmit).toHaveBeenCalled();
    expect(area, 'Enter adds no line break').toHaveValue('abc1');
  });

  it('shows the email error when leaving the field', async () => {
    renderControl({control: makeControl({type: 'email'})});
    const input = screen.getByRole('textbox', {name: 'The question'});
    await userEvent.type(input, 'name@example');
    expect(screen.queryByRole('alert')).toBeNull();
    await userEvent.tab();
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Enter a valid email address (e.g. name@example.com).',
    );
  });

  it('saves the initial ranking order at once and reorders with the buttons', async () => {
    const onValue = vi.fn();
    renderControl({
      control: makeControl({type: 'ranking', options: options('a', 'b', 'c')}),
      onValue,
    });
    expect(onValue, 'the initial order is a valid answer').toHaveBeenCalledWith(
      ['a', 'b', 'c'],
    );
    await userEvent.click(screen.getByRole('button', {name: 'Move C up'}));
    expect(onValue).toHaveBeenLastCalledWith(['a', 'c', 'b']);
  });

  it('fills a table, adds rows and removes them', async () => {
    const onValue = vi.fn();
    renderControl({
      control: makeControl({
        type: 'table',
        options: [
          {label: 'Name', value: 'name', visibility: []},
          {label: 'Role', value: 'role', visibility: []},
        ],
      }),
      onValue,
    });
    expect(
      screen.getByRole('columnheader', {name: 'Name'}),
    ).toBeInTheDocument();
    await userEvent.type(
      screen.getByRole('textbox', {name: 'Name, Row 1'}),
      'Ana',
    );
    expect(onValue).toHaveBeenLastCalledWith([{name: 'Ana'}]);
    expect(screen.getByRole('button', {name: 'Remove Row 1'})).toBeDisabled();

    await userEvent.click(screen.getByRole('button', {name: 'Add a row'}));
    await userEvent.type(
      screen.getByRole('textbox', {name: 'Role, Row 2'}),
      'CEO',
    );
    expect(onValue).toHaveBeenLastCalledWith([{name: 'Ana'}, {role: 'CEO'}]);

    await userEvent.click(screen.getByRole('button', {name: 'Remove Row 1'}));
    expect(onValue).toHaveBeenLastCalledWith([{role: 'CEO'}]);
  });

  it("labels a table's fixed rows and keeps one answer per row", async () => {
    const onValue = vi.fn();
    renderControl({
      control: makeControl({
        type: 'table',
        options: [{label: 'Sales', value: 'sales', visibility: []}],
        rows: ['January', 'February'],
      }),
      onValue,
    });
    expect(
      screen.getByRole('rowheader', {name: 'February'}),
    ).toBeInTheDocument();
    expect(screen.queryByRole('button', {name: 'Add a row'})).toBeNull();
    fireEvent.change(screen.getByRole('textbox', {name: 'Sales, February'}), {
      target: {value: '12'},
    });
    expect(onValue).toHaveBeenLastCalledWith([{}, {sales: '12'}]);
  });
});
