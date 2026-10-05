import {IconButton, Icon} from '@shared/ui';
import type {ReactNode} from 'react';
import './tags.css';

/**
 * A questionnaire's tags as small chips. With `onRemove` every chip has its × button (named by `removeLabel`), disabled
 * with `disabledReason` for read-only users; `renderTag` draws a chip's text (e.g. with the search highlighted). Nothing
 * is rendered when there are no tags.
 */
export function TagList({
  tags,
  label,
  onRemove,
  removeLabel,
  disabledReason,
  renderTag,
}: {
  tags: string[];
  /** The list's accessible name ("Tags"). */
  label: string;
  onRemove?: (tag: string) => void;
  removeLabel?: (tag: string) => string;
  disabledReason?: string | null;
  renderTag?: (tag: string) => ReactNode;
}) {
  if (tags.length === 0) {
    return null;
  }
  return (
    <ul className="tag-list" aria-label={label}>
      {tags.map((tag) => (
        <li key={tag} className="tag-chip">
          <span className="tag-chip__text">
            {renderTag ? renderTag(tag) : tag}
          </span>
          {onRemove ? (
            <IconButton
              size="sm"
              className="tag-chip__remove"
              label={removeLabel ? removeLabel(tag) : tag}
              icon={<Icon name="close" size={12} />}
              disabledReason={disabledReason}
              onClick={() => onRemove(tag)}
            />
          ) : null}
        </li>
      ))}
    </ul>
  );
}
