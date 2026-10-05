import {testI18n} from '@shared/i18n/testing';
import {fireEvent, render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it, vi} from 'vitest';
import {FileControl} from './FileControl';

const upload = vi.hoisted(() => vi.fn());
const download = vi.hoisted(() => vi.fn());
vi.mock('../api/upload', () => ({
  uploadAnswerFile: upload,
  downloadTemplate: download,
}));

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
    expect(
      screen.queryByText(/^\d+ (\/ \d+ )?files?$/),
      'no counter before the first file, and never "of 2": the limit is a cap, not a target',
    ).toBeNull();
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
    expect(screen.getByText('1 file')).toBeInTheDocument();
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

  it("offers the question's template to download, fill in and upload", async () => {
    download.mockResolvedValue(undefined);
    render(
      <I18nextProvider i18n={testI18n('respondent')}>
        <FileControl
          label="Attach"
          value={[]}
          max={2}
          disabled={false}
          target={target}
          template={{
            key: 'templates/ACME0001/u/budget.csv',
            filename: 'budget.csv',
          }}
          token="tok"
          onChange={vi.fn()}
          onBusyChange={vi.fn()}
        />
      </I18nextProvider>,
    );
    expect(screen.getByText('Use the template')).toBeInTheDocument();
    await userEvent.click(
      screen.getByRole('button', {name: 'Download the template budget.csv'}),
    );
    expect(download).toHaveBeenCalledWith(
      'templates/ACME0001/u/budget.csv',
      'tok',
    );
  });

  it('says so when the template cannot be downloaded', async () => {
    download.mockRejectedValue(new Error('404'));
    render(
      <I18nextProvider i18n={testI18n('respondent')}>
        <FileControl
          label="Attach"
          value={[]}
          max={2}
          disabled={false}
          target={target}
          template={{
            key: 'templates/ACME0001/u/budget.csv',
            filename: 'budget.csv',
          }}
          onChange={vi.fn()}
          onBusyChange={vi.fn()}
        />
      </I18nextProvider>,
    );
    await userEvent.click(
      screen.getByRole('button', {name: 'Download the template budget.csv'}),
    );
    expect(
      await screen.findByText(
        'Could not download the template. Please try again.',
      ),
    ).toBeInTheDocument();
  });

  it('adds a screenshot pasted with Ctrl+V, each paste as one more file, with its thumbnail (§9.9)', async () => {
    upload.mockResolvedValueOnce('k/1.png').mockResolvedValueOnce('k/2.png');
    // jsdom has no object URLs.
    URL.createObjectURL = vi.fn(() => 'blob:thumb');
    URL.revokeObjectURL = vi.fn();
    const {onChange} = renderFiles(3);
    const screenshot = () =>
      new File(['png'], 'image.png', {type: 'image/png'});
    fireEvent.paste(document, {clipboardData: {files: [screenshot()]}});
    // Some browsers list a copied screenshot only among the clipboard's items.
    fireEvent.paste(document, {
      clipboardData: {
        files: [],
        items: [{kind: 'file', getAsFile: () => screenshot()}],
      },
    });
    await waitFor(() =>
      expect(onChange).toHaveBeenLastCalledWith(['k/1.png', 'k/2.png']),
    );
    expect(screen.getByText('2 files')).toBeInTheDocument();
    expect(
      screen.getAllByText(/^screenshot-\d{8}-\d{6}(-\d+)?\.png$/),
    ).toHaveLength(2);
    expect(
      document.querySelectorAll('img.files__thumb[src="blob:thumb"]'),
    ).toHaveLength(2);
  });
});
