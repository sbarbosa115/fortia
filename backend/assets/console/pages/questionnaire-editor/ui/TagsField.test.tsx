import {testI18n} from '@shared/i18n/testing';
import {render, screen, within} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import {useState} from 'react';
import {I18nextProvider} from 'react-i18next';
import {describe, expect, it} from 'vitest';
import {TagsField} from './TagsField';

function Harness({
  initial = [],
  disabledReason = null,
}: {
  initial?: string[];
  disabledReason?: string | null;
}) {
  const [tags, setTags] = useState(initial);
  return (
    <TagsField tags={tags} onChange={setTags} disabledReason={disabledReason} />
  );
}

function renderField(props: Parameters<typeof Harness>[0] = {}) {
  return render(
    <I18nextProvider i18n={testI18n('console')}>
      <Harness {...props} />
    </I18nextProvider>,
  );
}

function chips(): string[] {
  const list = screen.queryByRole('list', {name: 'Tags'});
  return list
    ? within(list)
        .getAllByRole('listitem')
        .map((item) => item.textContent ?? '')
    : [];
}

describe('TagsField', () => {
  it('adds a tag with Enter and several with commas, skipping repeats', async () => {
    renderField();
    const input = screen.getByRole('textbox', {name: 'Tags'});

    await userEvent.type(input, ' AP-03 {Enter}');
    await userEvent.type(input, 'NP-12, ap-03,Some,');

    expect(chips()).toEqual(['AP-03', 'NP-12', 'Some']);
    expect(input).toHaveValue('');
  });

  it('removes a tag with its × button, and the last one with Backspace in the empty field', async () => {
    renderField({initial: ['AP-03', 'NP-12', 'Some']});

    await userEvent.click(
      screen.getByRole('button', {name: 'Remove tag NP-12'}),
    );
    expect(chips()).toEqual(['AP-03', 'Some']);

    await userEvent.type(
      screen.getByRole('textbox', {name: 'Tags'}),
      '{Backspace}',
    );
    expect(chips()).toEqual(['AP-03']);
  });

  it('says why a tag is not added, keeping the text to fix it', async () => {
    renderField({
      initial: Array.from({length: 20}, (_, i) => `T${i + 1}`),
    });
    const input = screen.getByRole('textbox', {name: 'Tags'});

    await userEvent.type(input, 'One more{Enter}');

    expect(screen.getByRole('alert')).toHaveTextContent(
      'A questionnaire has at most 20 tags.',
    );
    expect(chips()).toHaveLength(20);
    expect(input).toHaveValue('One more');
  });

  it('is disabled with the reason for read-only users', () => {
    renderField({
      initial: ['AP-03'],
      disabledReason: "Your read-only role can't make changes.",
    });

    const input = screen.getByRole('textbox', {name: 'Tags'});
    expect(input).toBeDisabled();
    expect(input).toHaveAccessibleDescription(
      "Your read-only role can't make changes.",
    );
    expect(
      screen.getByRole('button', {name: 'Remove tag AP-03'}),
    ).toBeDisabled();
  });
});
