import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {DocumentationPage} from './DocumentationPage';

const mocks = vi.hoisted(() => ({get: vi.fn()}));

vi.mock('@shared/api', async (original) => ({
  ...(await original<typeof import('@shared/api')>()),
  api: {get: mocks.get, post: vi.fn(), put: vi.fn(), delete: vi.fn()},
}));

const VIDEO = {
  id: 'v1',
  title: 'Getting started with Mappi',
  description: 'A tour of the console.',
  url: 'https://www.youtube.com/watch?v=abcDEF12345',
  language: 'en',
  category: 'getting-started',
  order: 1,
  duration_minutes: 4,
  created_at: '2026-09-01T10:00:00Z',
  updated_at: '2026-09-01T10:00:00Z',
};

function renderPage(language: 'en' | 'es' = 'en', url = '/documentation') {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console', language)}>
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[url]}>
          <DocumentationPage />
        </MemoryRouter>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('DocumentationPage guides (PRD §10.18)', () => {
  beforeEach(() => mocks.get.mockReset());

  it('lists the 15 guides with their topic and reading time', () => {
    renderPage();

    expect(screen.getAllByRole('article')).toHaveLength(15);
    expect(screen.getByText('15 guides')).toBeInTheDocument();
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
    expect(screen.getByText('15 guías')).toBeInTheDocument();
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

    expect(screen.getAllByRole('article')).toHaveLength(15);
    expect(screen.getByRole('searchbox', {name: 'Search guides'})).toHaveValue(
      '',
    );
  });
});

describe('DocumentationPage videos (PRD §10.18)', () => {
  beforeEach(() => mocks.get.mockReset());

  it('asks for the videos of the UI language and embeds them in privacy-enhanced mode', async () => {
    mocks.get.mockResolvedValue({videos: [VIDEO]});

    renderPage('en', '/documentation?tab=videos');

    const card = await screen.findByRole('article', {
      name: 'Getting started with Mappi',
    });
    expect(mocks.get).toHaveBeenCalledWith('/videos', {
      query: {language: 'en'},
    });
    expect(
      within(card).getByTitle('Video: Getting started with Mappi'),
    ).toHaveAttribute(
      'src',
      'https://www.youtube-nocookie.com/embed/abcDEF12345',
    );
    expect(within(card).getByText('4 min')).toBeInTheDocument();
    expect(
      within(card).getByText('A tour of the console.'),
    ).toBeInTheDocument();
  });

  it('asks for the Spanish videos in Spanish', async () => {
    mocks.get.mockResolvedValue({videos: []});

    renderPage('es', '/documentation?tab=videos');

    await screen.findByText('Aún no hay videos');
    expect(mocks.get).toHaveBeenCalledWith('/videos', {
      query: {language: 'es'},
    });
  });

  it('opens the videos tab from the tabs and offers the guides when there are no videos', async () => {
    mocks.get.mockResolvedValue({videos: []});
    const user = userEvent.setup();
    renderPage();

    await user.click(screen.getByRole('tab', {name: 'Videos'}));
    expect(await screen.findByText('No videos yet')).toBeInTheDocument();
    await user.click(screen.getByRole('button', {name: 'Read the guides'}));

    expect(screen.getByRole('tab', {name: 'Guides'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
    expect(screen.getAllByRole('article')).toHaveLength(15);
  });

  it('shows the error with a retry', async () => {
    mocks.get.mockRejectedValueOnce(
      new ApiError(500, 'INTERNAL_ERROR', 'Boom'),
    );
    const user = userEvent.setup();
    renderPage('en', '/documentation?tab=videos');

    expect(await screen.findByRole('alert')).toBeInTheDocument();
    mocks.get.mockResolvedValue({videos: [VIDEO]});
    await user.click(screen.getByRole('button', {name: 'Try again'}));

    expect(
      await screen.findByRole('article', {name: 'Getting started with Mappi'}),
    ).toBeInTheDocument();
  });
});
