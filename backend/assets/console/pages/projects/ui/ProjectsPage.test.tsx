import {
  deleteProject,
  fetchProject,
  fetchProjects,
  type Project,
  type ProjectAssignation,
} from '@console/entities/project';
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
import {ProjectsPage} from './ProjectsPage';

const mocks = vi.hoisted(() => ({
  viewer: null as unknown as Viewer,
  feature: {loading: false, allowed: true, included: true, verdict: null},
}));

vi.mock('@console/entities/project', async (original) => ({
  ...(await original<typeof import('@console/entities/project')>()),
  fetchProjects: vi.fn(),
  fetchProject: vi.fn(),
  deleteProject: vi.fn(),
}));
vi.mock('@console/entities/viewer', () => ({useViewer: () => mocks.viewer}));
vi.mock('@console/entities/plan-usage', () => ({
  useFeature: () => mocks.feature,
}));

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
    name: 'Store opening Q4',
    description: null,
    due_date: '2099-12-01',
    created_at: '2026-09-01T10:00:00Z',
    updated_at: '2026-09-01T10:00:00Z',
    state: 'review',
    progress_percent: 69,
    completed_assignations: 1,
    approved_assignations: 0,
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
          <MemoryRouter>
            <ProjectsPage />
          </MemoryRouter>
        </ToastProvider>
      </QueryClientProvider>
    </I18nextProvider>,
  );
}

describe('ProjectsPage', () => {
  beforeEach(() => {
    mocks.viewer = viewer(true);
    mocks.feature = {
      loading: false,
      allowed: true,
      included: true,
      verdict: null,
    };
    fetchMock.mockReset();
  });

  it('shows a project row with its state, approvals, deadline and next step', async () => {
    fetchMock.mockResolvedValue(page([project()]));

    renderPage();

    const table = within(await screen.findByRole('table', {name: 'Projects'}));
    expect(table.getByText('Store opening Q4')).toBeInTheDocument();
    expect(table.getByText('Acme Retail')).toBeInTheDocument();
    expect(table.getByText('AR')).toBeInTheDocument();
    expect(table.getByText('Needs your review')).toBeInTheDocument();
    expect(table.getByText('0 of 2 approved')).toBeInTheDocument();
    expect(
      table.getByRole('progressbar', {name: 'Store opening Q4: 69% complete'}),
    ).toHaveAttribute('aria-valuenow', '69');
    expect(table.getByText(/^in \d+ days$/)).toBeInTheDocument();
    expect(table.getByRole('link', {name: 'Review answers'})).toHaveAttribute(
      'href',
      '/assignations/a-2',
    );
    expect(screen.getByText('1 project')).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'New project'})).toHaveAttribute(
      'href',
      '/projects/new',
    );
    expect(fetchMock).toHaveBeenCalledWith({
      status: null,
      q: '',
      page: 1,
      pageSize: 10,
    });
  });

  it('expands a row into its assignations', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'Show the assignations of Store opening Q4',
      }),
    );

    const detail = within(
      screen.getByRole('table', {name: 'Assignations of Store opening Q4'}),
    );
    expect(detail.getByText('Question 4 of 8')).toBeInTheDocument();
    expect(detail.getByText('4 of 4 questions')).toBeInTheDocument();
    expect(detail.getByText('1 of 4 reviewed')).toBeInTheDocument();
    expect(detail.getByText('Not complete yet')).toBeInTheDocument();
    expect(detail.getByRole('link', {name: 'Review'})).toHaveAttribute(
      'href',
      '/assignations/a-2',
    );
    expect(detail.getByRole('link', {name: 'Open'})).toHaveAttribute(
      'href',
      '/assignations/a-1',
    );
  });

  it('asks the API for the tab state and the search', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();
    await screen.findByText('Store opening Q4');

    await userEvent.click(screen.getByRole('tab', {name: 'In progress'}));
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith(
        expect.objectContaining({status: 'progress', page: 1}),
      ),
    );
    await userEvent.click(screen.getByRole('tab', {name: 'Completed'}));
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith(
        expect.objectContaining({status: 'approved'}),
      ),
    );

    await userEvent.type(
      screen.getByRole('searchbox', {name: 'Search projects'}),
      'retail',
    );
    await waitFor(() =>
      expect(fetchMock).toHaveBeenLastCalledWith(
        expect.objectContaining({q: 'retail', status: 'approved'}),
      ),
    );
  });

  it('says when the filters leave nothing and clears them', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();
    await screen.findByText('Store opening Q4');
    fetchMock.mockResolvedValue(page([]));

    await userEvent.click(screen.getByRole('tab', {name: 'Overdue'}));
    expect(
      await screen.findByRole('heading', {name: 'No projects match'}),
    ).toBeInTheDocument();

    fetchMock.mockResolvedValue(page([project()]));
    await userEvent.click(screen.getByRole('button', {name: 'Clear filters'}));
    expect(await screen.findByText('Store opening Q4')).toBeInTheDocument();
    expect(screen.getByRole('tab', {name: 'All'})).toHaveAttribute(
      'aria-selected',
      'true',
    );
  });

  it('explains the section when there are no projects', async () => {
    fetchMock.mockResolvedValue(page([]));

    renderPage();

    expect(
      await screen.findByRole('heading', {name: 'No projects yet'}),
    ).toBeInTheDocument();
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

  it('confirms before deleting and says the assignations are kept', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    vi.mocked(deleteProject).mockResolvedValue(undefined);
    renderPage();

    await userEvent.click(
      await screen.findByRole('button', {
        name: 'More actions for Store opening Q4',
      }),
    );
    await userEvent.click(screen.getByRole('menuitem', {name: 'Delete'}));

    const dialog = within(screen.getByRole('dialog'));
    expect(dialog.getByText('Delete this project?')).toBeInTheDocument();
    expect(
      dialog.getByText(/Its assignations and their answers are kept/),
    ).toBeInTheDocument();
    await userEvent.click(dialog.getByRole('button', {name: 'Delete project'}));
    await waitFor(() =>
      expect(vi.mocked(deleteProject)).toHaveBeenCalledWith('p-1'),
    );
  });

  it('validates the edit dialog: the deadline can move but not be cleared', async () => {
    fetchMock.mockResolvedValue(page([project()]));
    vi.mocked(fetchProject).mockResolvedValue(
      project({
        available_assignations: [
          {assignations_id: 'a-1', name: 'Store checklist', project_id: 'p-1'},
          {assignations_id: 'a-3', name: 'Training plan', project_id: null},
        ],
      }),
    );
    renderPage();
    await userEvent.click(
      await screen.findByRole('button', {
        name: 'More actions for Store opening Q4',
      }),
    );
    await userEvent.click(screen.getByRole('menuitem', {name: 'Edit'}));

    const dialog = within(screen.getByRole('dialog'));
    expect(dialog.getByLabelText('Organization')).toHaveValue('Acme Retail');
    expect(dialog.getByLabelText('Organization')).toBeDisabled();
    expect(
      await dialog.findByRole('checkbox', {name: 'Training plan'}),
    ).not.toBeChecked();
    expect(
      dialog.getByRole('checkbox', {name: 'Store checklist'}),
    ).toBeChecked();
    await userEvent.clear(dialog.getByLabelText(/Deadline/));
    await userEvent.clear(dialog.getByLabelText(/Name/));
    await userEvent.click(dialog.getByRole('button', {name: 'Save changes'}));

    expect(
      dialog.getByText('Choose a deadline for the project'),
    ).toBeInTheDocument();
    expect(dialog.getByText('Name is required')).toBeInTheDocument();
  });

  it('disables the changes for a read-only user, with the reason', async () => {
    mocks.viewer = viewer(false);
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'New project'}),
    ).toBeDisabled();
    expect(
      screen.getAllByText("Your read-only role can't create resources.").length,
    ).toBeGreaterThan(0);
    await userEvent.click(
      await screen.findByRole('button', {
        name: 'More actions for Store opening Q4',
      }),
    );
    expect(screen.getByRole('menuitem', {name: 'Edit'})).toBeDisabled();
    expect(screen.getByRole('menuitem', {name: 'Delete'})).toBeDisabled();
  });

  it('disables "New project" when the plan does not include assignations', async () => {
    mocks.feature = {
      loading: false,
      allowed: false,
      included: false,
      verdict: null,
    };
    fetchMock.mockResolvedValue(page([project()]));
    renderPage();

    expect(
      await screen.findByRole('button', {name: 'New project'}),
    ).toBeDisabled();
    expect(
      screen.getByText("Your plan doesn't include assignations."),
    ).toBeInTheDocument();
  });
});
