import {testI18n} from '@shared/i18n/testing';
import {render, screen} from '@testing-library/react';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it} from 'vitest';
import {fromSubmission} from '../model/results';
import {sanitizeDescription} from './OtherResults';
import {ResultsView} from './ResultsView';

function renderResults(result: Record<string, unknown>) {
  render(
    <I18nextProvider i18n={testI18n('respondent')}>
      <ResultsView data={fromSubmission('s1', 'ACME0001', result)} />
    </I18nextProvider>,
  );
}

const diagnostic = {
  type: 'diagnostic',
  score: {value: 7, max: 14},
  categories: [
    {id: 'usage', name: 'Usage', score: 4, max: 7},
    {id: 'gov', name: 'Governance', score: 3, max: 7},
  ],
  tiers: [
    {id: 'explorer', name: 'Explorer', min: 0, max: 4, visible: true},
    {
      id: 'practitioner',
      name: 'Practitioner',
      description: 'You run pilots.',
      min: 5,
      max: 9,
      visible: true,
    },
  ],
  recommendations: [
    {
      tier_id: 'explorer',
      recommendation: 'Start with one use case.',
      action: null,
      visible: true,
    },
  ],
  action_plan: [],
};

describe('ResultsView (PRD §9.12)', () => {
  it('shows a diagnostic: tier, overall score, areas and the lower tier recommendations', () => {
    renderResults({
      ...diagnostic,
      cta: {
        title: 'Talk to us',
        button: {text: 'Book a call', url: 'https://acme.test'},
      },
    });
    expect(
      screen.getByText('Thank you so much for your support'),
    ).toBeInTheDocument();
    expect(screen.getByText('Practitioner')).toBeInTheDocument();
    expect(
      screen.getByRole('progressbar', {name: 'Overall score'}),
    ).toHaveAttribute('aria-valuenow', '50');
    expect(screen.getByText('Score by area')).toBeInTheDocument();
    expect(
      screen.getByText('Start with one use case.'),
      'the nearest lower tier with recommendations',
    ).toBeInTheDocument();
    expect(
      screen.queryByText('Your category profile'),
      'no radar with < 3 areas',
    ).toBeNull();
    expect(screen.getByRole('button', {name: 'Download'})).toBeInTheDocument();
    expect(screen.getByRole('link', {name: /Book a call/})).toHaveAttribute(
      'target',
      '_blank',
    );
  });

  it('limits the blocks to the layout and uses result_copy', () => {
    renderResults({
      ...diagnostic,
      layout: ['score'],
      result_copy: {title: 'Your result', overall_score: 'Total'},
    });
    expect(screen.getByText('Your result')).toBeInTheDocument();
    expect(screen.getByRole('heading', {name: 'Total'})).toBeInTheDocument();
    expect(screen.queryByText('Score by area')).toBeNull();
    expect(screen.queryByText('Recommendations')).toBeNull();
    expect(screen.queryByRole('button', {name: 'Download'})).toBeNull();
  });

  it('shows the default thanks, with result_copy overrides', () => {
    renderResults({type: 'default', result_copy: {subtitle: 'See you soon'}});
    expect(
      screen.getByText('Your answers have been submitted successfully.'),
    ).toBeInTheDocument();
    expect(screen.getByText('See you soon')).toBeInTheDocument();
  });

  it('lists recommended products, hiding a $0.00 price', () => {
    renderResults({
      products: [
        {
          product_id: 'p1',
          name: 'Shoes',
          description: '<p>Nice <b>shoes</b></p>',
          price: 0,
          image_url: null,
          product_url: 'https://shop.test/p1',
        },
      ],
    });
    expect(screen.getByText('Your Recommended Products')).toBeInTheDocument();
    expect(screen.getByText('Top Match')).toBeInTheDocument();
    expect(screen.queryByText('$0.00')).toBeNull();
    expect(screen.getByRole('link', {name: /View in Store/})).toHaveAttribute(
      'href',
      'https://shop.test/p1',
    );
  });

  it('says when there are no products', () => {
    renderResults({products: []});
    expect(
      screen.getByText('No products recommended at this time.'),
    ).toBeInTheDocument();
  });

  it('says when the AI profile could not be generated', () => {
    renderResults({type: 'ai_team_profile'});
    expect(
      screen.getByText(
        "We couldn't generate your profile this time. Please try again later.",
      ),
    ).toBeInTheDocument();
  });

  it('sanitizes product HTML (D11)', () => {
    const clean = sanitizeDescription(
      '<p onclick="steal()">Hi<img src=x onerror=alert(1)><script>alert(2)</script><a href="javascript:x">l</a></p>',
    );
    expect(clean).toBe('<p>Hil</p>');
  });
});
