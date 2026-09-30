import {usePlanUsage} from '@console/entities/plan-usage';
import {useViewer, type Viewer} from '@console/entities/viewer';
import {testI18n} from '@shared/i18n/testing';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {QuestionnaireNewPage} from './QuestionnaireNewPage';

vi.mock('@console/entities/plan-usage', async (original) => ({
  ...(await original<typeof import('@console/entities/plan-usage')>()),
  usePlanUsage: vi.fn(),
}));
vi.mock('@console/entities/viewer', async (original) => ({
  ...(await original<typeof import('@console/entities/viewer')>()),
  useViewer: vi.fn(),
}));

function usage(
  features: Record<string, {allowed: boolean; reason?: string | null}>,
) {
  vi.mocked(usePlanUsage).mockReturnValue({
    data: {features},
    isPending: false,
    isError: false,
  } as unknown as ReturnType<typeof usePlanUsage>);
}

function renderPage() {
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <MemoryRouter initialEntries={['/questionnaires/new']}>
        <Routes>
          <Route
            path="/questionnaires/new"
            element={<QuestionnaireNewPage />}
          />
          <Route path="*" element={<p>{'at another page'}</p>} />
        </Routes>
      </MemoryRouter>
    </I18nextProvider>,
  );
}

describe('QuestionnaireNewPage', () => {
  beforeEach(() => {
    vi.mocked(useViewer).mockReturnValue({isAdmin: false} as Viewer);
  });

  it('offers the four types with their descriptions and labels', () => {
    usage({
      'regular': {allowed: true},
      'diagnostic': {allowed: true},
      'quiz-funnel': {allowed: true},
      'chain': {allowed: true},
    });
    renderPage();

    expect(
      screen.getByRole('heading', {name: 'What do you want to create?'}),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('radio', {name: /Regular \(Default\)/}),
    ).toHaveTextContent(
      'A classic questionnaire that ends with a custom thank-you message of your choice.',
    );
    expect(screen.getByRole('radio', {name: /Diagnostic/})).toHaveTextContent(
      'Best for assessments',
    );
    expect(screen.getByRole('radio', {name: /Quiz Funnel/})).toHaveTextContent(
      'Imports from your store',
    );
    expect(screen.getByRole('radio', {name: /Chaining/})).toHaveTextContent(
      'Best for AI generation',
    );
  });

  it('disables a type the plan does not allow, with the limit text', () => {
    usage({
      'regular': {allowed: true},
      'diagnostic': {allowed: false, reason: 'FEATURE_NOT_IN_PLAN'},
      'quiz-funnel': {allowed: false, reason: 'FEATURE_LIMIT_REACHED'},
      'chain': {allowed: true},
    });
    renderPage();

    const diagnostic = screen.getByRole('radio', {name: /Diagnostic/});
    expect(diagnostic).toBeDisabled();
    expect(diagnostic).toHaveTextContent(
      "Your plan doesn't include this feature.",
    );
    expect(screen.getByRole('radio', {name: /Quiz Funnel/})).toHaveTextContent(
      "You've reached your plan's limit for this feature.",
    );
    expect(screen.getByRole('radio', {name: /Regular/})).toBeEnabled();
  });

  it('opens the editor of the chosen type', async () => {
    usage({
      'regular': {allowed: true},
      'diagnostic': {allowed: true},
      'quiz-funnel': {allowed: true},
      'chain': {allowed: true},
    });
    renderPage();

    await userEvent.click(screen.getByRole('radio', {name: /Chaining/}));

    expect(screen.getByText('at another page')).toBeInTheDocument();
  });
});
