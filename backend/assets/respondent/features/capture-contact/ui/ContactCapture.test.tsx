import {testI18n} from '@shared/i18n/testing';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it, vi} from 'vitest';
import {phoneInput} from '../model/capture';
import {ContactCapture} from './ContactCapture';

function renderCapture(submitting = false) {
  const onSubmit = vi.fn();
  render(
    <I18nextProvider i18n={testI18n('respondent')}>
      <ContactCapture submitting={submitting} onSubmit={onSubmit} />
    </I18nextProvider>,
  );
  return onSubmit;
}

describe('ContactCapture (PRD §9.6)', () => {
  it('only accepts digits and a leading + in the phone while typing', () => {
    expect(phoneInput('+57 (300) abc 123')).toBe('+57300123');
    expect(phoneInput('300+1')).toBe('3001');
  });

  it('shows the errors on submit and clears them when a field is edited', async () => {
    const onSubmit = renderCapture();
    expect(
      screen.getByText('Where should we send your results?'),
    ).toBeInTheDocument();
    await userEvent.click(screen.getByRole('button', {name: 'See my results'}));
    expect(onSubmit).not.toHaveBeenCalled();
    expect(screen.getByText('Enter your name.')).toBeInTheDocument();
    await userEvent.type(screen.getByRole('textbox', {name: /Name/}), 'Ana');
    expect(screen.queryByText('Enter your name.')).toBeNull();
    expect(
      screen.getByText('Enter a valid email address (e.g. name@example.com).'),
    ).toBeInTheDocument();
  });

  it('attaches name, email and phone', async () => {
    const onSubmit = renderCapture();
    await userEvent.type(
      screen.getByRole('textbox', {name: /Name/}),
      'Ana Gómez',
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: /Email/}),
      'ana@acme.test',
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: /Phone/}),
      '+57 3001234567',
    );
    await userEvent.click(screen.getByRole('button', {name: 'See my results'}));
    expect(onSubmit).toHaveBeenCalledWith({
      name: 'Ana Gómez',
      email: 'ana@acme.test',
      phone: '+573001234567',
    });
  });

  it('cannot be sent twice while submitting', () => {
    renderCapture(true);
    expect(screen.getByRole('button', {name: /See my results/})).toBeDisabled();
  });
});
