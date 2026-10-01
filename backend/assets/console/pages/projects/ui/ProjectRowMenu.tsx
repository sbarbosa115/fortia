import {Icon, type IconName, Tooltip} from '@shared/ui';
import {useEffect, useId, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';

/**
 * The ⋯ menu of a project row: Edit project, a separator, Delete project. Escape and a click outside close it; for
 * a read-only user both items are disabled with the reason.
 */
export function ProjectRowMenu({
  name,
  changeReason,
  onEdit,
  onDelete,
}: {
  name: string;
  changeReason: string | null;
  onEdit: () => void;
  onDelete: () => void;
}) {
  const {t} = useTranslation('pages.projects');
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);
  const menuId = useId();

  useEffect(() => {
    if (!open) {
      return;
    }
    const onPointer = (event: MouseEvent) => {
      if (!ref.current?.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onPointer);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onPointer);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  const item = (
    icon: IconName,
    label: string,
    action: () => void,
    danger = false,
  ) => {
    const button = (
      <button
        type="button"
        role="menuitem"
        className="row-menu__item"
        data-danger={danger || undefined}
        disabled={Boolean(changeReason)}
        onClick={() => {
          setOpen(false);
          action();
        }}
      >
        <Icon name={icon} size={16} />
        {label}
      </button>
    );
    return changeReason ? (
      <Tooltip content={changeReason}>{button}</Tooltip>
    ) : (
      button
    );
  };

  return (
    <div className="row-menu" ref={ref}>
      <button
        type="button"
        className="row-menu__trigger"
        aria-label={t('moreActions', {name})}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => setOpen((value) => !value)}
      >
        <Icon name="ellipsis" size={18} />
      </button>
      {open ? (
        <div className="row-menu__list" role="menu" id={menuId}>
          {item('square-pen', t('edit'), onEdit)}
          <div className="row-menu__separator" role="separator" />
          {item('trash-2', t('delete'), onDelete, true)}
        </div>
      ) : null}
    </div>
  );
}
