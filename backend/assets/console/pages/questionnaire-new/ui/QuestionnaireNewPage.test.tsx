import {testI18n} from '@shared/i18n/testing';
import {render, screen} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {describe, expect, it} from 'vitest';
import {QuestionnaireNewPage} from './QuestionnaireNewPage';

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
  it('offers the four types with their descriptions and labels', () => {
    renderPage();

    expect(
      screen.getByRole('heading', {name: 'What do you want to create?'}),
    ).toBeInTheDocument();
    const regular = screen.getByRole('radio', {name: /Regular/});
    expect(regular).toHaveTextContent(
      'A classic questionnaire that ends with a custom thank-you message of your choice.',
    );
    expect(regular).toHaveTextContent('Default');
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

  it('opens the editor of the chosen type on Continue', async () => {
    renderPage();

    await userEvent.click(screen.getByRole('radio', {name: /Chaining/}));
    expect(screen.getByRole('radio', {name: /Chaining/})).toBeChecked();
    expect(screen.queryByText('at another page')).not.toBeInTheDocument();

    await userEvent.click(screen.getByRole('button', {name: /Continue/}));

    expect(screen.getByText('at another page')).toBeInTheDocument();
  });

  it('clears the selection with Back, which disables Continue', async () => {
    renderPage();

    await userEvent.click(screen.getByRole('button', {name: /Back/}));

    expect(screen.getByRole('radio', {name: /Regular/})).not.toBeChecked();
    expect(screen.getByRole('button', {name: /Continue/})).toBeDisabled();
  });
});
