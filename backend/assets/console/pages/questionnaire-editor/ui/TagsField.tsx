import {
  addTags,
  MAX_TAG_LENGTH,
  MAX_TAGS,
  TagList,
} from '@console/entities/questionnaire';
import {Field, TextInput} from '@shared/ui';
import {type KeyboardEvent, useState} from 'react';
import {useTranslation} from 'react-i18next';

/**
 * The questionnaire's tags: type one and press Enter or a comma to add it as a chip (pasting "a, b" adds both), ×
 * removes it, Backspace in the empty field removes the last one. Read-only users see the chips with the reason the
 * field is disabled.
 */
export function TagsField({
  tags,
  onChange,
  disabledReason,
}: {
  tags: string[];
  onChange: (tags: string[]) => void;
  disabledReason: string | null;
}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const [text, setText] = useState('');
  const [error, setError] = useState<'tooMany' | 'tooLong' | null>(null);

  /** Adds the tags in `raw`; answers whether it all went in (else the text stays, to fix it). */
  const commit = (raw: string): boolean => {
    const result = addTags(tags, raw);
    setError(result.error);
    if (result.tags.length !== tags.length) {
      onChange(result.tags);
    }
    return result.error === null;
  };

  const handleChange = (value: string) => {
    if (!value.includes(',')) {
      setText(value);
      return;
    }
    // Everything before the last comma becomes tags; what follows it is still being typed.
    const cut = value.lastIndexOf(',');
    commit(value.slice(0, cut));
    setText(value.slice(cut + 1));
  };

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      event.preventDefault();
      if (commit(text)) {
        setText('');
      }
    } else if (event.key === 'Backspace' && text === '' && tags.length > 0) {
      onChange(tags.slice(0, -1));
      setError(null);
    }
  };

  const remove = (tag: string) => {
    onChange(tags.filter((existing) => existing !== tag));
    setError(null);
  };

  return (
    <div className="tags-field">
      <Field
        label={t('details.tagsLabel')}
        hint={disabledReason ?? t('details.tagsHint', {max: MAX_TAGS})}
        error={
          error
            ? t(`details.tagsErrors.${error}`, {
                max: error === 'tooMany' ? MAX_TAGS : MAX_TAG_LENGTH,
              })
            : null
        }
      >
        <TextInput
          value={text}
          maxLength={MAX_TAG_LENGTH * 4}
          placeholder={t('details.tagsPlaceholder')}
          disabled={disabledReason !== null}
          onChange={(e) => handleChange(e.target.value)}
          onKeyDown={handleKeyDown}
          onBlur={() => {
            if (text.trim() !== '' && commit(text)) {
              setText('');
            }
          }}
        />
      </Field>
      <TagList
        tags={tags}
        label={t('details.tagsLabel')}
        onRemove={remove}
        removeLabel={(tag) => t('details.removeTag', {tag})}
        disabledReason={disabledReason}
      />
    </div>
  );
}
