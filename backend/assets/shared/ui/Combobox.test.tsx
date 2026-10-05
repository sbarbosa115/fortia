import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {describe, expect, it, vi} from 'vitest';
import {Combobox} from './Combobox';

function Harness({onChange}: {onChange: (value: string) => void}) {
  const [value, setValue] = useState('');
  return (
    <Combobox
      label="Tag"
      value={value}
      onChange={(next) => {
        setValue(next);
        onChange(next);
      }}
      options={['Formación', 'Finanzas', 'RRHH']}
      placeholder="All tags"
      noMatches="No tag matches"
      clearLabel="Clear the tag filter"
    />
  );
}

describe('Combobox', () => {
  it('narrows the options as the user types, ignoring case and accents, and picks one with the keyboard', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    const field = screen.getByRole('combobox', {name: 'Tag'});

    await userEvent.type(field, 'FORMA');
    expect(
      screen.getAllByRole('option').map((option) => option.textContent),
    ).toEqual(['Formación']);
    await userEvent.keyboard('{Enter}');

    expect(onChange).toHaveBeenLastCalledWith('Formación');
    expect(field).toHaveValue('Formación');
    expect(screen.queryByRole('listbox')).toBeNull();
  });

  it('says when nothing matches, and Escape keeps the chosen option', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    const field = screen.getByRole('combobox', {name: 'Tag'});

    await userEvent.type(field, 'zzz');
    expect(screen.getByText('No tag matches')).toBeInTheDocument();
    await userEvent.keyboard('{Escape}');

    expect(field, 'the typed text is never the value').toHaveValue('');
    expect(onChange).not.toHaveBeenCalled();
  });

  it('clears the choice with its button', async () => {
    const onChange = vi.fn();
    render(<Harness onChange={onChange} />);
    const field = screen.getByRole('combobox', {name: 'Tag'});
    await userEvent.click(field);
    await userEvent.click(screen.getByRole('option', {name: 'RRHH'}));

    await userEvent.click(
      screen.getByRole('button', {name: 'Clear the tag filter'}),
    );

    expect(onChange).toHaveBeenLastCalledWith('');
    expect(field).toHaveValue('');
  });
});
