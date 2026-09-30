import {testI18n} from '@shared/i18n/testing';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {MemoryRouter} from 'react-router';
import {describe, expect, it} from 'vitest';
import {NotFoundPage} from './NotFoundPage';

describe('NotFoundPage', () => {
  it('says the page does not exist and offers the way home', () => {
    render(
      <I18nextProvider i18n={testI18n('console')}>
        <MemoryRouter>
          <NotFoundPage />
        </MemoryRouter>
      </I18nextProvider>,
    );

    expect(
      screen.getByRole('heading', {name: /Oops! Page not found/}),
    ).toBeInTheDocument();
    expect(screen.getByRole('link', {name: 'Return to Home'})).toHaveAttribute(
      'href',
      '/ai-experience',
    );
  });
});
