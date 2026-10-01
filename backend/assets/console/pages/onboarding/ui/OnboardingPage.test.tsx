import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes, useLocation} from 'react-router';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {OnboardingPage} from './OnboardingPage';

const mocks = vi.hoisted(() => ({
  get: vi.fn(),
  post: vi.fn(),
  put: vi.fn(),
  patch: vi.fn(),
  viewer: {customerId: 'NEWCO001', canWrite: true, isAdmin: false},
}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {
    get: mocks.get,
    post: mocks.post,
    put: mocks.put,
    patch: mocks.patch,
    delete: vi.fn(),
  },
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

function Location() {
  const location = useLocation();
  return <p>{`at ${location.pathname}`}</p>;
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={['/onboarding']}>
            <Routes>
              <Route path="/onboarding" element={<OnboardingPage />} />
              <Route path="*" element={<Location />} />
            </Routes>
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

async function toTemplateStep() {
  await userEvent.click(screen.getByRole('radio', {name: /Diagnose/}));
  await userEvent.click(screen.getByRole('button', {name: 'Continue'}));
  await userEvent.type(
    screen.getByRole('textbox', {name: /Workspace name/}),
    'Newco',
  );
  await userEvent.selectOptions(
    screen.getByRole('combobox', {name: /Language/}),
    'en-US',
  );
  await userEvent.type(
    screen.getByRole('textbox', {name: /Website/}),
    'newco.test',
  );
  await userEvent.click(screen.getByRole('button', {name: 'Continue'}));
  await screen.findByRole('heading', {name: 'Start from a template'});
}

describe('OnboardingPage (PRD §10.3)', () => {
  beforeEach(() => {
    localStorage.clear();
    mocks.viewer.canWrite = true;
    mocks.get.mockReset();
    mocks.post.mockReset().mockResolvedValue({questionnaire_id: 'q-1'});
    mocks.put.mockReset().mockResolvedValue(null);
    mocks.patch
      .mockReset()
      .mockImplementation((path: string) =>
        Promise.resolve(
          path === '/customer/workspace'
            ? {name: 'Newco', language: 'en-US', website: 'https://newco.test'}
            : path === '/customer/onboarding'
              ? {onboarding_completed: true}
              : {},
        ),
      );
  });
  afterEach(() => vi.restoreAllMocks());

  it('shows the duration, the 7-step progress and keeps Continue disabled until a goal is chosen', async () => {
    renderPage();

    expect(screen.getByText('8–12 minutes')).toBeInTheDocument();
    expect(
      screen.getByRole('progressbar', {name: 'Setup progress'}),
    ).toHaveAttribute('aria-valuemax', '7');
    expect(screen.getByText('Step 1 of 7')).toBeInTheDocument();
    const next = screen.getByRole('button', {name: 'Continue'});
    expect(next, 'PRD §10.3: disabled until a goal is chosen').toBeDisabled();

    await userEvent.click(
      screen.getByRole('radio', {name: /Qualify or recommend/}),
    );
    expect(next).toBeEnabled();
  });

  it('saves the workspace (D15) and recommends the template of the goal in the workspace language', async () => {
    renderPage();
    await toTemplateStep();

    expect(mocks.patch).toHaveBeenCalledWith('/customer/workspace', {
      name: 'Newco',
      language: 'en-US',
      website: 'https://newco.test',
    });
    expect(
      screen.getByRole('heading', {name: 'AI Maturity Diagnostic'}),
    ).toBeInTheDocument();
    expect(screen.getByText('8 questions')).toBeInTheDocument();
  });

  it('creates the questionnaire through POST /questionnaire once the checklist is complete', async () => {
    renderPage();
    await toTemplateStep();
    await userEvent.click(screen.getByRole('button', {name: 'Use template'}));

    const save = () => screen.getByRole('button', {name: 'Save and publish'});
    expect(save()).toBeDisabled();
    await userEvent.click(screen.getByRole('button', {name: 'Confirm title'}));
    const first = screen.getByRole('textbox', {name: 'Question 1'});
    await userEvent.clear(first);
    await userEvent.type(first, 'Do we have an AI plan?');
    await userEvent.click(screen.getByRole('button', {name: 'Looks good'}));
    expect(save()).toBeEnabled();

    await userEvent.click(save());
    const dialog = await screen.findByRole('dialog');
    await userEvent.click(
      within(dialog).getByRole('button', {name: 'Create questionnaire'}),
    );

    await screen.findByRole('heading', {name: 'Publish it'});
    const [path, body] = mocks.post.mock.calls[0] as [
      string,
      {
        slug: string;
        states: {
          parameters: {
            questionnaire: {title: string; questions: {title: string}[]};
          };
        }[];
      },
    ];
    expect(path).toBe('/questionnaire');
    expect(body.slug).toMatch(/^ai-maturity-diagnostic-[0-9a-f]{4}$/);
    expect(body.states[0]?.parameters.questionnaire.questions[0]?.title).toBe(
      'Do we have an AI plan?',
    );
    expect(screen.getByRole('textbox', {name: /Custom link/})).toHaveValue(
      body.slug,
    );
  });

  it('publishes with the edited slug, activates it and opens the test', async () => {
    const open = vi.spyOn(window, 'open').mockReturnValue(null);
    renderPage();
    await toTemplateStep();
    await userEvent.click(screen.getByRole('button', {name: 'Use template'}));
    await userEvent.click(screen.getByRole('button', {name: 'Confirm title'}));
    const first = screen.getByRole('textbox', {name: 'Question 1'});
    await userEvent.type(first, '!');
    await userEvent.click(screen.getByRole('button', {name: 'Looks good'}));
    await userEvent.click(
      screen.getByRole('button', {name: 'Save and publish'}),
    );
    await userEvent.click(
      within(await screen.findByRole('dialog')).getByRole('button', {
        name: 'Create questionnaire',
      }),
    );
    const slug = await screen.findByRole('textbox', {name: /Custom link/});
    await userEvent.clear(slug);
    await userEvent.type(slug, 'my-diagnostic');
    await userEvent.click(
      screen.getByRole('button', {name: 'Publish and open test'}),
    );

    await screen.findByRole('heading', {name: 'Try it as a respondent'});
    expect(mocks.put).toHaveBeenCalledWith(
      '/questionnaire',
      expect.objectContaining({questionnaire_id: 'q-1', slug: 'my-diagnostic'}),
    );
    expect(mocks.patch).toHaveBeenCalledWith('/questionnaire/q-1', {
      is_active: true,
    });
    expect(open).toHaveBeenLastCalledWith(
      '/f/my-diagnostic?test=1',
      '_blank',
      'noopener',
    );
  });

  it('"Explore on my own" sets the flag and goes to /ai-experience', async () => {
    renderPage();
    await userEvent.click(
      screen.getByRole('button', {name: 'Explore on my own'}),
    );

    expect(await screen.findByText('at /ai-experience')).toBeInTheDocument();
    expect(mocks.patch).toHaveBeenCalledWith('/customer/onboarding', {
      completed: true,
    });
  });

  it('stays and says so when the flag cannot be saved', async () => {
    mocks.patch.mockRejectedValue(new Error('offline'));
    renderPage();
    await userEvent.click(
      screen.getByRole('button', {name: 'Explore on my own'}),
    );

    expect(await screen.findByRole('alert')).toHaveTextContent(
      "We couldn't finish your setup",
    );
  });

  it('"Start from scratch →" finishes onboarding and opens the new questionnaire screen', async () => {
    renderPage();
    await toTemplateStep();
    await userEvent.click(
      screen.getByRole('button', {name: 'Start from scratch →'}),
    );

    expect(
      await screen.findByText('at /questionnaires/new'),
    ).toBeInTheDocument();
  });

  it('disables the workspace save for a read-only member', async () => {
    mocks.viewer.canWrite = false;
    renderPage();
    await userEvent.click(screen.getByRole('radio', {name: /Diagnose/}));
    await userEvent.click(screen.getByRole('button', {name: 'Continue'}));
    await userEvent.type(
      screen.getByRole('textbox', {name: /Workspace name/}),
      'Newco',
    );

    expect(screen.getByRole('button', {name: 'Continue'})).toBeDisabled();
    expect(screen.getByRole('note')).toHaveTextContent('read-only');
  });
});
