import {testI18n} from '@shared/i18n/testing';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it, vi} from 'vitest';
import {AudioControl} from './AudioControl';
import {AudioTutorial} from './AudioTutorial';

function renderAudio(value: string[] = [], onChange = vi.fn()) {
  render(
    <I18nextProvider i18n={testI18n('respondent')}>
      <AudioControl
        label="Tell us"
        value={value}
        disabled={false}
        language="en"
        onChange={onChange}
        onBusyChange={() => undefined}
      />
    </I18nextProvider>,
  );
  return onChange;
}

describe('AudioControl (PRD §9.7)', () => {
  it('invites to record, and says when the connection is not secure', async () => {
    renderAudio();
    expect(screen.getByText('Tap to record your answer')).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Tap to record your answer'}),
    );
    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Recording requires a secure (HTTPS) connection.',
    );
  });

  it('lists the recordings, with "No transcription available." for an empty one', async () => {
    const onChange = renderAudio(['First answer', '']);
    expect(screen.getByText('First answer')).toBeInTheDocument();
    expect(screen.getByText('No transcription available.')).toBeInTheDocument();
    await userEvent.click(
      screen.getAllByRole('button', {name: 'Remove recording'})[0]!,
    );
    expect(onChange).toHaveBeenCalledWith(['']);
    expect(
      screen.getByRole('button', {name: 'Record again from scratch'}),
    ).toBeInTheDocument();
  });

  it('lets a respondent without a microphone type the answer (D12)', async () => {
    const onChange = renderAudio();
    await userEvent.click(
      screen.getByRole('button', {
        name: "Can't record? Type your answer instead",
      }),
    );
    await userEvent.type(
      screen.getByRole('textbox', {name: 'Your answer'}),
      'It went well',
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Add to my answer'}),
    );
    expect(onChange).toHaveBeenCalledWith(['It went well']);
  });
});

describe('AudioTutorial (PRD §9.8, D12)', () => {
  it('can always be skipped', async () => {
    const onDone = vi.fn();
    render(
      <I18nextProvider i18n={testI18n('respondent')}>
        <AudioTutorial language="en" onDone={onDone} />
      </I18nextProvider>,
    );
    expect(screen.getByText("Let's test your microphone")).toBeInTheDocument();
    expect(
      screen.getByText('Hello, my microphone is working great.'),
    ).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Skip the mic check'}),
    );
    expect(onDone).toHaveBeenCalled();
  });
});
