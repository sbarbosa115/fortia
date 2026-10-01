import {Button, Icon, IconButton} from '@shared/ui';
import {useEffect, useId, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';

/**
 * The ⋯ menu of a project row (PRD §10.12): Edit and Delete. Escape and a click outside close it; for a read-only
 * user both items are disabled with the reason.
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

  const choose = (action: () => void) => () => {
    setOpen(false);
    action();
  };

  return (
    <div className="projects__menu" ref={ref}>
      <IconButton
        size="sm"
        icon={<Icon name="more" />}
        label={t('menu.label', {name})}
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={open ? menuId : undefined}
        onClick={() => setOpen((value) => !value)}
      />
      {open ? (
        <div className="projects__menu-list" role="menu" id={menuId}>
          <Button
            role="menuitem"
            size="sm"
            variant="ghost"
            icon={<Icon name="edit" size={16} />}
            disabledReason={changeReason}
            onClick={choose(onEdit)}
          >
            {t('menu.edit')}
          </Button>
          <Button
            role="menuitem"
            size="sm"
            variant="ghost"
            icon={<Icon name="trash" size={16} />}
            disabledReason={changeReason}
            onClick={choose(onDelete)}
          >
            {t('menu.delete')}
          </Button>
        </div>
      ) : null}
    </div>
  );
}
