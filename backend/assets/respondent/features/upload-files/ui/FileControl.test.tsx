import {testI18n} from '@shared/i18n/testing';
import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it, vi} from 'vitest';
import {FileControl} from './FileControl';

const upload = vi.hoisted(() => vi.fn());
vi.mock('../api/upload', () => ({uploadAnswerFile: upload}));

const target = {customerId: 'ACME0001', sessionId: 's1', questionId: 'q1'};

function renderFiles(max = 2, value: string[] = []) {
  const onChange = vi.fn();
  const onBusyChange = vi.fn();
  const {container} = render(
    <I18nextProvider i18n={testI18n('respondent')}>
      <FileControl
        label="Attach"
        value={value}
        max={max}
        disabled={false}
        target={target}
        onChange={onChange}
        onBusyChange={onBusyChange}
      />
    </I18nextProvider>,
  );
  const input = container.querySelector('input[type=file]') as HTMLInputElement;
  return {onChange, onBusyChange, input};
}

describe('FileControl (PRD §9.9)', () => {
  it('uploads a chosen file and saves its object key', async () => {
    upload.mockResolvedValue('ACME0001/s1/q1/abc.pdf');
    const {onChange, input} = renderFiles();
    expect(screen.getByText('0 / 2 files')).toBeInTheDocument();
    await userEvent.upload(input, new File(['x'], 'report.pdf'));
    await waitFor(() =>
      expect(onChange).toHaveBeenLastCalledWith(['ACME0001/s1/q1/abc.pdf']),
    );
    expect(upload).toHaveBeenCalledWith(
      expect.any(File),
      target,
      expect.any(Function),
      null,
    );
    expect(screen.getByText('1 / 2 files')).toBeInTheDocument();
  });

  it('offers Try again after a failed upload', async () => {
    upload.mockRejectedValueOnce(new Error('down'));
    const {input} = renderFiles();
    await userEvent.upload(input, new File(['x'], 'a.png'));
    expect(
      await screen.findByText('Could not upload your file. Please try again.'),
    ).toBeInTheDocument();
    upload.mockResolvedValue('ACME0001/s1/q1/a.png');
    await userEvent.click(screen.getByRole('button', {name: 'Try again'}));
    await waitFor(() =>
      expect(
        screen.queryByText('Could not upload your file. Please try again.'),
      ).toBeNull(),
    );
  });

  it('says how many files were not added beyond the limit', async () => {
    upload.mockResolvedValue('k');
    const {input} = renderFiles(1);
    await userEvent.upload(input, [
      new File(['x'], 'a.pdf'),
      new File(['y'], 'b.pdf'),
    ]);
    expect(
      screen.getByText('1 file was not added: you can attach up to 1 files.'),
    ).toBeInTheDocument();
    expect(
      screen.getByText(
        "You've reached the limit of 1 files. Remove one to add another.",
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('button', {name: 'Remove a.pdf'}),
    ).toBeInTheDocument();
  });
});
