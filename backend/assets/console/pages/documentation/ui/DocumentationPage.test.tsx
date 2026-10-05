import {testI18n} from '@shared/i18n/testing';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {describe, expect, it} from 'vitest';
import {DocumentationPage} from './DocumentationPage';

function renderPage(language: 'en' | 'es' = 'en') {
  return render(
    <I18nextProvider i18n={testI18n('console', language)}>
      <MemoryRouter initialEntries={['/documentation']}>
        <DocumentationPage />
      </MemoryRouter>
    </I18nextProvider>,
  );
}

describe('DocumentationPage guides (PRD §10.18)', () => {
  it('lists the 14 guides with their topic and reading time', () => {
    renderPage();

    expect(screen.getAllByRole('article')).toHaveLength(14);
    expect(screen.getByText('14 guides')).toBeInTheDocument();
    const welcome = screen.getByRole('article', {name: 'Welcome to Mappi'});
    expect(within(welcome).getByText('Getting started')).toBeInTheDocument();
    expect(within(welcome).getByText(/min read/)).toBeInTheDocument();
    expect(
      within(welcome).getByRole('link', {name: 'Welcome to Mappi'}),
    ).toHaveAttribute('href', '/documentation/guides/welcome');
  });

  it('shows the guides in Spanish when the console is in Spanish', () => {
    renderPage('es');

    expect(
      screen.getByRole('article', {name: 'Bienvenido a Mappi'}),
    ).toBeInTheDocument();
    expect(screen.getByText('14 guías')).toBeInTheDocument();
  });

  it('searches ignoring case and accents', async () => {
    const user = userEvent.setup();
    renderPage('es');

    await user.type(
      screen.getByRole('searchbox', {name: 'Buscar guías'}),
      'ORGANIZACION plantilla',
    );

    expect(
      screen
        .getAllByRole('article')
        .map((a) => a.getAttribute('aria-labelledby')),
    ).toEqual(['guide-organizations-and-members']);
  });

  it('filters by topic', async () => {
    const user = userEvent.setup();
    renderPage();

    await user.selectOptions(
      screen.getByRole('combobox', {name: 'Topic'}),
      'Analytics',
    );

    expect(screen.getAllByRole('article')).toHaveLength(2);
    expect(
      screen.getByRole('article', {name: 'Read the dashboard'}),
    ).toBeInTheDocument();
  });

  it('says when nothing matches and clears every filter', async () => {
    const user = userEvent.setup();
    renderPage();
    await user.selectOptions(
      screen.getByRole('combobox', {name: 'Topic'}),
      'Account',
    );
    await user.type(
      screen.getByRole('searchbox', {name: 'Search guides'}),
      'zzzz',
    );

    expect(screen.getByText('No guides match your search')).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Clear filters'}));

    expect(screen.getAllByRole('article')).toHaveLength(14);
    expect(screen.getByRole('searchbox', {name: 'Search guides'})).toHaveValue(
      '',
    );
  });
});
