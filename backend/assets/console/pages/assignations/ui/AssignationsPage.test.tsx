import {
  deleteProject,
  fetchProjects,
  type Project,
  type ProjectAssignation,
  updateProject,
} from '@console/entities/project';
import {
  fetchQuestionnaires,
  type QuestionnairePage,
} from '@console/entities/questionnaire';
import type {Viewer} from '@console/entities/viewer';
import {ApiError} from '@shared/api';
import {testI18n} from '@shared/i18n/testing';
import {ToastProvider} from '@shared/ui';
import {QueryClient, QueryClientProvider} from '@tanstack/react-query';
import {render, screen, waitFor, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {AssignationsPage} from './AssignationsPage';

const mocks = vi.hoisted(() => ({
  viewer: null as unknown as Viewer,
}));

vi.mock('@console/entities/project', async (original) => ({
  ...(await original<typeof import('@console/entities/project')>()),
  fetchProjects: vi.fn(),
  deleteProject: vi.fn(),
  updateProject: vi.fn(),
}));
vi.mock('@console/entities/questionnaire', async (original) => ({
  ...(await original<typeof import('@console/entities/questionnaire')>()),
  fetchQuestionnaires: vi.fn(),
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));

const fetchMock = vi.mocked(fetchProjects);

function viewer(canWrite: boolean): Viewer {
  return {
    signedIn: true,
    userId: 'u',
    email: canWrite ? 'owner@acme.test' : 'reader@acme.test',
    name: 'Ana',
    customerId: 'ACME0001',
    role: canWrite ? 'Customer-Admin' : 'Customer-Read-Only',
    isRoot: canWrite,
    isAdmin: false,
    isPlatformAdmin: false,
    canWrite,
    assumed: null,
  };
}

function assignation(
  overrides: Partial<ProjectAssignation> = {},
): ProjectAssignation {
  return {
    assignations_id: 'a-1',
    name: 'Store checklist',
    questionnaire_id: 'q-1',
    active: true,
    state: 'progress',
    completed: false,
    review_status: 'not_ready',
    requires_review: true,
    attempt: 1,
    due_date: '2099-12-01',
    overdue: false,
    percent: 38,
    progress: {completed: 3, total: 8, unit: 'questions', current_question: 4},
    review: {reviewed: 0, total: 8, approved: 0, rejected: 0},
    ...overrides,
  };
}

function project(overrides: Partial<Project> = {}): Project {
  return {
    project_id: 'p-1',
    customer_id: 'ACME0001',
    organization_id: 'o-1',
    organization_name: 'Acme Retail',
    requires_review: true,
    name: 'Store opening Q4',
    description: null,
    due_date: '2099-12-01',
    created_at: '2026-09-01T10:00:00Z',
    updated_at: '2026-09-01T10:00:00Z',
    state: 'review',
    progress_percent: 69,
    completed_assignations: 1,
    approved_assignations: 0,
    done_assignations: 0,
    total_assignations: 2,
    assignations: [
      assignation(),
      assignation({
        assignations_id: 'a-2',
        name: 'Visual review',
        state: 'review',
        completed: true,
        review_status: 'in_review',
        percent: 100,
        progress: {
          completed: 4,
          total: 4,
          unit: 'questions',
          current_question: null,
        },
        review: {reviewed: 1, total: 4, approved: 1, rejected: 0},
      }),
    ],
    available_assignations: null,
    ...overrides,
  };
}

function page(projects: Project[], total = projects.length) {
  return {
    projects,
    pagination: {
      page: 1,
      page_size: 10,
      total_items: total,
      total_pages: Math.ceil(total / 10),
      has_next: total > 10,
      has_previous: false,
    },
  };
}

function renderPage() {
  const client = new QueryClient({defaultOptions: {queries: {retry: false}}});
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <QueryClientProvider client={client}>
        <ToastProvider>
          <MemoryRouter initialEntries={['/assignations']}>
            <AssignationsPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('AssignationsPage', () => {
  beforeEach(() => {
    mocks.viewer = viewer(true);
    fetchMock.mockReset();
  });

  it('shows an assignation row with its status, approvals, deadline and next step', async () => {
    fetchMock.mockResolvedValue(page([project()]));

    renderPage();

    const table = within(
      await screen.findByRole('table', {name: 'Assignations'}),
    );
    expect(table.getByText('Store opening Q4')).toBeInTheDocument();
    expect(table.getByText(/^Acme Retail, created /)).toBeInTheDocument();
    expect(table.getByText('AR')).toBeInTheDocument();
    expect(table.getByText('Needs your review')).toBeInTheDocument();
    expect(table.getByText('0 of 2 approved')).toBeInTheDocument();
    expect(
      table.getByRole('progressbar', {name: '0 of 2 approved'}),
    ).toHaveAttribute('aria-valuenow', '0');
    expect(table.getByText(/^in \d+ days$/)).toBeInTheDocument();
    expect(table.getByRole('link', {name: 'Review answers'})).toHaveAttribute(
      'href',
      '/assignations/a-2?from=%2Fassignations',
    );
    expect(screen.getByText('1–1 of 1')).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'New assignation'})).toHaveAttribute(
      'href',
      '/assignations/new',
    );
    expect(fetchMock).toHaveBeenCalledWith({
      status: null,
      q: '',
      page: 1,
      pageSize: 10,
    });
  });

  it('expands a row into its questionnaires', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'Show the questionnaires of Store opening Q4',
      }),
    );

    const detail = within(
      screen.getByRole('table', {name: 'Questionnaires of Store opening Q4'}),
    );
    expect(detail.getByText('Question 4 of 8')).toBeInTheDocument();
    expect(detail.getByText('Completed')).toBeInTheDocument();
    expect(detail.getByText('1 of 4 reviewed')).toBeInTheDocument();
    expect(detail.getByText('Not ready for review yet')).toBeInTheDocument();
    expect(
      detail.getByRole('link', {name: 'Open the questionnaire Visual review'}),
      'returns to the assignations',
    ).toHaveAttribute('href', '/assignations/a-2?from=%2Fassignations');
    expect(
      detail.getByRole('link', {
        name: 'Open the questionnaire Store checklist',
      }),
    ).toHaveAttribute('href', '/assignations/a-1?from=%2Fassignations');
    expect(detail.getByText('Review', {selector: 'a'})).toHaveAttribute(
      'href',
      '/assignations/a-2?from=%2Fassignations',
    );
    expect(
      detail.getByRole('button', {
        name: 'Edit the questionnaire of Visual review',
      }),
      'its questions can grow while the assignation runs',
    ).toBeEnabled();
  });

  it('asks the API for the status pill and the search', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();
    await screen.findByText('Store opening Q4');

    await userEvent.click(screen.getByRole('button', {name: 'In progress'}));
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith(
        expect.objectContaining({status: 'progress', page: 1}),
      ),
    );
    await userEvent.click(screen.getByRole('button', {name: 'Completed'}));
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith(
        expect.objectContaining({status: 'completed'}),
      ),
    );

    await userEvent.type(
      screen.getByRole('searchbox', {
        name: 'Search assignation or organization',
      }),
      'retail',
    );
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith(
        expect.objectContaining({q: 'retail', status: 'completed'}),
      ),
    );
  });

  it('says when the filters leave nothing and clears them', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();
    await screen.findByText('Store opening Q4');
    fetchMock.mockResolvedValue(page([]));

    await userEvent.click(screen.getByRole('button', {name: 'Overdue'}));
    expect(
      await screen.findByText('No assignation matches this filter.'),
    ).toBeInTheDocument();

    fetchMock.mockResolvedValue(page([project()]));
    await userEvent.click(screen.getByRole('button', {name: 'Clear filters'}));
    expect(await screen.findByText('Store opening Q4')).toBeInTheDocument();
    expect(screen.getByRole('button', {name: 'All'})).toHaveAttribute(
      'aria-pressed',
      'true',
    );
  });

  it('explains the section when there are no assignations', async () => {
    fetchMock.mockResolvedValue(page([]));

    renderPage();

    expect(
      await screen.findByText(
        /^No assignations yet\. Create one to send questionnaires/,
      ),
    ).toBeInTheDocument();
    expect(
      screen.getByRole('link', {name: 'Create the first assignation'}),
    ).toHaveAttribute('href', '/assignations/new');
  });

  it('shows the error with a retry', async () => {
    fetchMock.mockRejectedValue(
      new ApiError(500, 'INTERNAL_ERROR', 'Boom', undefined),
    );

    renderPage();

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Something went wrong',
    );
    expect(screen.getByRole('button', {name: 'Try again'})).toBeInTheDocument();
  });

  it('shows the loading state first', () => {
    fetchMock.mockReturnValue(new Promise(() => {}));

    renderPage();

    expect(screen.queryByRole('table')).not.toBeInTheDocument();
    expect(screen.getByRole('status')).toBeInTheDocument();
  });

  it('confirms before deleting and says the questionnaires are kept', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    vi.mocked(deleteProject).mockResolvedValue(undefined);
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'More actions for Store opening Q4',
      }),
    );
    await userEvent.click(
      screen.getByRole('menuitem', {name: 'Delete assignation'}),
    );

    const dialog = within(screen.getByRole('dialog'));
    expect(dialog.getByText('Delete this assignation?')).toBeInTheDocument();
    expect(
      dialog.getByText(/Its questionnaires and their answers are kept/),
    ).toBeInTheDocument();
    await userEvent.click(
      dialog.getByRole('button', {name: 'Delete assignation'}),
    );
    await waitFor(() =>
      expect(vi.mocked(deleteProject)).toHaveBeenCalledWith('p-1'),
    );
  });

  it('validates the edit dialog: the deadline can move but not be cleared', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();
    await userEvent.click(
      await screen.findByRole('button', {
        name: 'More actions for Store opening Q4',
      }),
    );
    await userEvent.click(
      screen.getByRole('menuitem', {name: 'Edit assignation'}),
    );

    const dialog = within(screen.getByRole('dialog'));
    expect(dialog.getByLabelText(/Organization/)).toHaveValue('Acme Retail');
    expect(dialog.getByLabelText(/Organization/)).toHaveAttribute('readonly');
    await userEvent.clear(dialog.getByLabelText(/Deadline/));
    await userEvent.clear(dialog.getByLabelText(/Assignation name/));
    await userEvent.click(dialog.getByRole('button', {name: 'Save'}));

    expect(
      dialog.getByText('Choose a deadline for the assignation'),
    ).toBeInTheDocument();
    expect(
      dialog.getByText('The assignation name is required'),
    ).toBeInTheDocument();
  });

  it('adds and removes questionnaires from the edit dialog', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    vi.mocked(updateProject).mockResolvedValue(project());
    vi.mocked(fetchQuestionnaires).mockResolvedValue({
      items: [
        {questionnaire_id: 'q-1', title: 'Store checklist', question_count: 8},
        {questionnaire_id: 'q-5', title: 'Supplier audit', question_count: 6},
        {questionnaire_id: 'q-6', title: 'Draft', question_count: 0},
      ],
      total: 3,
      total_pages: 1,
    } as unknown as QuestionnairePage);
    renderPage();
    await userEvent.click(
      await screen.findByRole('button', {
        name: 'More actions for Store opening Q4',
      }),
    );
    await userEvent.click(
      screen.getByRole('menuitem', {name: 'Edit assignation'}),
    );
    const dialog = within(screen.getByRole('dialog'));

    await userEvent.click(
      dialog.getByRole('button', {name: 'Remove Visual review'}),
    );
    expect(dialog.getByText('Will be removed')).toBeInTheDocument();
    await userEvent.click(
      dialog.getByRole('button', {name: 'Add questionnaires'}),
    );
    expect(
      await dialog.findByRole('button', {name: 'Add Supplier audit'}),
    ).toBeEnabled();
    expect(
      dialog.queryByRole('button', {name: 'Add Store checklist'}),
      'a questionnaire it already has is not offered',
    ).not.toBeInTheDocument();
    expect(
      dialog.getByRole('button', {name: 'Add Draft'}),
      'a questionnaire without questions cannot be added',
    ).toBeDisabled();
    await userEvent.click(
      dialog.getByRole('button', {name: 'Add Supplier audit'}),
    );
    expect(dialog.getByRole('switch', {name: 'Supplier audit'})).toBeChecked();
    expect(
      dialog.queryByRole('button', {name: 'Add Supplier audit'}),
    ).not.toBeInTheDocument();
    await userEvent.click(dialog.getByRole('button', {name: 'Save'}));

    await waitFor(() =>
      expect(vi.mocked(updateProject)).toHaveBeenCalledWith(
        'p-1',
        expect.objectContaining({
          assignation_ids: ['a-1'],
          review_assignation_ids: ['a-1'],
          questionnaire_ids: ['q-5'],
          review_questionnaire_ids: ['q-5'],
          registration_title: 'Tell us who you are',
        }),
      ),
    );
  });

  it('disables the changes for a read-only user, with the reason', async () => {
    mocks.viewer = viewer(false);
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'New assignation'}),
    ).toBeDisabled();
    expect(
      screen.getAllByText("Your read-only role can't create resources.").length,
    ).toBeGreaterThan(0);
    await userEvent.click(
      await screen.findByRole('button', {
        name: 'More actions for Store opening Q4',
      }),
    );
    expect(
      screen.getByRole('menuitem', {name: 'Edit assignation'}),
    ).toBeDisabled();
    expect(
      screen.getByRole('menuitem', {name: 'Delete assignation'}),
    ).toBeDisabled();
    expect(
      screen.getAllByText("Your read-only role can't make changes.").length,
    ).toBeGreaterThan(0);
  });
});
