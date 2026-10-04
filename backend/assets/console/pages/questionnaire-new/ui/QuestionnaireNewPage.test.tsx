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
          <Route
            path="/questionnaires/create/regular"
            element={<p>{'at the regular editor'}</p>}
          />
          <Route path="*" element={<p>{'at another page'}</p>} />
        </Routes>
      </MemoryRouter>
    </I18nextProvider>,
  );
}

describe('QuestionnaireNewPage', () => {
  it('offers only the regular type, selected by default', () => {
    renderPage();

    expect(
      screen.getByRole('heading', {name: 'What do you want to create?'}),
    ).toBeInTheDocument();
    const regular = screen.getByRole('radio', {name: /Regular/});
    expect(regular).toHaveTextContent(
      'A classic questionnaire that ends with a custom thank-you message of your choice.',
    );
    expect(regular).toHaveTextContent('Default');
    expect(regular).toBeChecked();
    expect(screen.getAllByRole('radio')).toHaveLength(1);
    expect(screen.queryByText(/Diagnostic/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Quiz Funnel/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Chaining/)).not.toBeInTheDocument();
  });

  it('opens the regular editor on Continue', async () => {
    renderPage();

    await userEvent.click(screen.getByRole('button', {name: /Continue/}));

    expect(screen.getByText('at the regular editor')).toBeInTheDocument();
  });

  it('clears the selection with Back, which disables Continue', async () => {
    renderPage();

    await userEvent.click(screen.getByRole('button', {name: /Back/}));

    expect(screen.getByRole('radio', {name: /Regular/})).not.toBeChecked();
    expect(screen.getByRole('button', {name: /Continue/})).toBeDisabled();
  });
});
