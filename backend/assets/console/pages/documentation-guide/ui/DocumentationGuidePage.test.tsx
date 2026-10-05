import {testI18n} from '@shared/i18n/testing';
import {fireEvent, render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter, Route, Routes} from 'react-router';
import {describe, expect, it} from 'vitest';
import {DocumentationGuidePage} from './DocumentationGuidePage';

function renderGuide(guideId: string, language: 'en' | 'es' = 'en') {
  return render(
    <I18nextProvider i18n={testI18n('console', language)}>
      <MemoryRouter initialEntries={[`/documentation/guides/${guideId}`]}>
        <Routes>
          <Route
            path="/documentation/guides/:guideId"
            element={<DocumentationGuidePage />}
          />
        </Routes>
      </MemoryRouter>
    </I18nextProvider>,
  );
}

describe('DocumentationGuidePage (PRD §10.18)', () => {
  it('shows the guide with its topic, reading time and sections', () => {
    renderGuide('dashboard');

    expect(
      screen.getByRole('heading', {level: 1, name: 'Read the dashboard'}),
    ).toBeInTheDocument();
    expect(screen.getByText('Analytics')).toBeInTheDocument();
    expect(screen.getByText(/min read/)).toBeInTheDocument();
    expect(
      screen.getByRole('heading', {level: 2, name: 'The funnel'}),
    ).toBeInTheDocument();
    expect(screen.getByText(/promoters 9–10/)).toBeInTheDocument();
  });

  it('has a table of contents that links to every section', () => {
    renderGuide('dashboard');

    const toc = screen.getByRole('navigation', {name: 'On this page'});
    const links = within(toc).getAllByRole('link');
    expect(links.map((link) => link.textContent)).toEqual([
      'Open the dashboard',
      'The summary',
      'The funnel',
      'Charts per question',
    ]);
    expect(links[2]).toHaveAttribute('href', '#funnel');
    fireEvent.click(links[2]!);
    expect(screen.getByRole('heading', {name: 'The funnel'})).toHaveFocus();
  });

  it('shows the screenshot of the reader language', () => {
    renderGuide('dashboard', 'es');

    expect(
      screen.getByRole('img', {name: 'El tablero de un cuestionario'}),
    ).toHaveAttribute('src', '/docs/screenshots/es/dashboard.png');
  });

  it('hides a screenshot that fails to load', () => {
    renderGuide('dashboard');

    fireEvent.error(
      screen.getByRole('img', {name: 'The dashboard of a questionnaire'}),
    );

    expect(screen.queryByRole('img')).not.toBeInTheDocument();
  });

  it('links to the previous and next guide and moves between them', async () => {
    const user = userEvent.setup();
    renderGuide('first-questionnaire');

    const pager = screen.getByRole('navigation', {name: 'More guides'});
    expect(
      within(pager).getByRole('link', {
        name: /Previous guide.*Welcome to Mappi/,
      }),
    ).toHaveAttribute('href', '/documentation/guides/welcome');
    await user.click(
      within(pager).getByRole('link', {
        name: /Next guide.*Question types/,
      }),
    );

    expect(
      screen.getByRole('heading', {level: 1, name: 'Question types'}),
    ).toBeInTheDocument();
  });

  it('has no previous link on the first guide and no next link on the last', () => {
    const {unmount} = renderGuide('welcome');
    expect(screen.queryByRole('link', {name: /Previous guide/})).toBeNull();
    unmount();

    renderGuide('system-settings');
    expect(screen.queryByRole('link', {name: /Next guide/})).toBeNull();
    expect(
      screen.getByRole('link', {name: /Previous guide/}),
    ).toBeInTheDocument();
  });

  it('shows the guide in Spanish with a breadcrumb back to the documentation', () => {
    renderGuide('assignations', 'es');

    expect(
      screen.getByRole('heading', {
        level: 1,
        name: 'Envía cuestionarios con asignaciones',
      }),
    ).toBeInTheDocument();
    const crumbs = screen.getByRole('navigation', {name: 'Ruta de navegación'});
    expect(
      within(crumbs).getByRole('link', {name: 'Documentación'}),
    ).toHaveAttribute('href', '/documentation');
  });

  it('says when the guide does not exist', () => {
    renderGuide('nope');

    expect(screen.getByText('Guide not found')).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Back to the documentation'}),
    ).toHaveAttribute('href', '/documentation');
  });
});
