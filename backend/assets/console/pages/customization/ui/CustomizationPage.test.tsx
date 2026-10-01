import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {CustomizationPage} from './CustomizationPage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  pollJob: vi.fn(),
  viewer: {customerId: 'ACME0001', canWrite: true, isAdmin: false},
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, post: mocks.post, put: vi.fn(), delete: vi.fn()},
  pollJob: mocks.pollJob,
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

const STORED = {
  website: 'https://acme.test',
  styles: {
    logoUrl: 'https://acme.test/logo.svg',
    font: {family: 'Lora'},
    button: {primary: {background: '#1d4ed8', color: '#ffffff'}},
  },
};

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <CustomizationPage />
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('CustomizationPage (PRD §10.13)', () => {
  beforeEach(() => {
    mocks.get.mockReset().mockResolvedValue(STORED);
    mocks.post
      .mockReset()
      .mockResolvedValue({job: {job_id: 'job-1', status: 'PENDING'}});
    mocks.pollJob.mockReset().mockResolvedValue({type: 'styles'});
    mocks.viewer.canWrite = true;
  });

  it('loads the stored brand into the form', async () => {
    renderPage();

    expect(await screen.findByLabelText('Website URL')).toHaveValue(
      'https://acme.test',
    );
    expect(screen.getByLabelText('Logo URL')).toHaveValue(
      'https://acme.test/logo.svg',
    );
    expect(screen.getByLabelText('Font')).toHaveValue('Lora');
    expect(screen.getByLabelText(/^Brand color/)).toHaveValue('#1d4ed8');
    expect(mocks.get).toHaveBeenCalledWith('/styles', {
      query: {customer_id: 'ACME0001'},
    });
    expect(
      screen.getByRole('group', {name: /preview of a respondent screen/i}),
    ).toBeInTheDocument();
  });

  it('saves the website and the styles when the website did not change, then polls the job every 5 s for 2 min', async () => {
    const user = userEvent.setup();
    renderPage();
    const color = await screen.findByLabelText(/^Brand color/);

    await user.clear(color);
    await user.type(color, '#8249df');
    await user.click(screen.getByRole('button', {name: 'Save'}));

    await screen.findByText('Styles updated successfully');
    expect(mocks.post).toHaveBeenCalledWith('/styles', {
      website: 'https://acme.test',
      styles: expect.objectContaining({
        a: {color: '#8249df'},
        button: {
          primary: {
            background: '#8249df',
            backgroundHover: '#7240c4',
            color: '#FFFFFF',
          },
        },
      }),
    });
    expect(mocks.pollJob).toHaveBeenCalledWith(
      'job-1',
      expect.objectContaining({intervalMs: 5000, timeoutMs: 120000}),
    );
  });

  it('sends only the website when it changed and says so before saving', async () => {
    const user = userEvent.setup();
    renderPage();
    const website = await screen.findByLabelText('Website URL');

    await user.clear(website);
    await user.type(website, 'https://new-brand.test');
    expect(
      screen.getByText(/we'll read this website and design your styles/i),
    ).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Save'}));

    await waitFor(() =>
      expect(mocks.post).toHaveBeenCalledWith('/styles', {
        website: 'https://new-brand.test',
      }),
    );
  });

  it('resets the brand locally to the defaults without saving', async () => {
    const user = userEvent.setup();
    renderPage();
    await screen.findByLabelText('Website URL');

    await user.click(screen.getByRole('button', {name: 'Reset'}));

    expect(screen.getByText('Reset to default values')).toBeInTheDocument();
    expect(screen.getByLabelText('Font')).toHaveValue('Montserrat');
    expect(screen.getByLabelText(/^Brand color/)).toHaveValue('#18181b');
    expect(screen.getByLabelText('Logo URL')).toHaveValue('');
    expect(screen.getByLabelText('Website URL')).toHaveValue(
      'https://acme.test',
    );
    expect(mocks.post).not.toHaveBeenCalled();
  });

  it('blocks saving an invalid brand colour', async () => {
    const user = userEvent.setup();
    renderPage();
    const color = await screen.findByLabelText(/^Brand color/);

    await user.clear(color);
    await user.type(color, '#12');
    await user.click(screen.getByRole('button', {name: 'Save'}));

    expect(
      await screen.findByText(/Enter a color as # followed by 6/),
    ).toBeInTheDocument();
    expect(mocks.post).not.toHaveBeenCalled();
  });

  it('shows the error the job failed with', async () => {
    const {ApiError} = await import('@shared/api');
    mocks.pollJob.mockRejectedValue(
      new ApiError(200, 'WEBSITE_UNREACHABLE', 'nope'),
    );
    const user = userEvent.setup();
    renderPage();
    await screen.findByLabelText('Website URL');

    await user.click(screen.getByRole('button', {name: 'Save'}));

    expect(
      await screen.findByText(/could not read that website/i),
    ).toBeInTheDocument();
  });

  it('disables saving for a read-only user, with the reason', async () => {
    mocks.viewer.canWrite = false;
    renderPage();
    await screen.findByLabelText('Website URL');

    expect(screen.getByRole('button', {name: 'Save'})).toBeDisabled();
    expect(screen.getByLabelText('Website URL')).toBeDisabled();
  });

  it('shows an error with a retry when the brand cannot be read', async () => {
    mocks.get.mockRejectedValue(new Error('down'));
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'Try again'}),
    ).toBeInTheDocument();
  });
});
