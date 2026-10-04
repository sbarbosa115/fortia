import {useViewer} from '@console/entities/viewer';
import {AssumeCustomer} from '@console/widgets/assume-customer';
import {Icon, type IconName} from '@shared/ui';
import {useEffect, useRef, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, NavLink} from 'react-router';

type Entry = {to: string; key: string; icon: IconName; highlight?: boolean};

/** The admin console's groups and icons: design the experience, then send it and follow it up, then settings. */
const GROUPS: Array<{key: string; entries: Entry[]}> = [
  {
    key: 'design',
    entries: [
      {to: '/ai-experience', key: 'aiExperience', icon: 'bot', highlight: true},
      {
        to: '/questionnaires/new',
        key: 'designExperience',
        icon: 'sparkles',
      },
      {to: '/questionnaires', key: 'questionnaires', icon: 'clipboard-list'},
      {to: '/customization', key: 'customization', icon: 'palette'},
    ],
  },
  {
    key: 'send',
    entries: [
      {to: '/organizations', key: 'organizations', icon: 'building-2'},
      {to: '/assignations', key: 'assignations', icon: 'list-checks'},
    ],
  },
  {
    key: 'settings',
    entries: [
      {to: '/users', key: 'users', icon: 'user-plus'},
      {to: '/profile', key: 'profile', icon: 'user-cog'},
      {to: '/documentation', key: 'documentation', icon: 'graduation-cap'},
    ],
  },
];

const LANGUAGES = ['en', 'es'] as const;

/** "ana.owner@acme.test" → "Ana Owner" (PRD §10.1: the name is derived from the email). */
export function nameFromEmail(email: string): string {
  const local = email.split('@')[0] ?? '';
  return local
    .split(/[._-]+/)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

function initials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('');
}

/** The language switcher: a nav-like button opening a small menu above it. */
function LanguageMenu() {
  const {t, i18n} = useTranslation('app');
  const [open, setOpen] = useState(false);
  const root = useRef<HTMLDivElement>(null);
  const current = (i18n.resolvedLanguage ?? i18n.language ?? 'es').split(
    '-',
  )[0];

  useEffect(() => {
    if (!open) {
      return undefined;
    }
    const close = (event: MouseEvent | KeyboardEvent) => {
      if (
        event instanceof KeyboardEvent
          ? event.key === 'Escape'
          : !root.current?.contains(event.target as Node)
      ) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', close);
    document.addEventListener('keydown', close);
    return () => {
      document.removeEventListener('mousedown', close);
      document.removeEventListener('keydown', close);
    };
  }, [open]);

  return (
    <div className="sidebar__language" ref={root}>
      <button
        type="button"
        className="sidebar__link sidebar__language-button"
        aria-label={t('language.label')}
        aria-haspopup="menu"
        aria-expanded={open}
        onClick={() => setOpen(!open)}
      >
        <Icon name="globe" size={17} />
        <span className="sidebar__language-current">
          {t(`language.${current === 'en' ? 'en' : 'es'}`)}
        </span>
        <span className="sidebar__language-chevron">
          <Icon name="chevrons-up-down" size={14} />
        </span>
      </button>
      {open ? (
        <div className="sidebar__menu" role="menu">
          {LANGUAGES.map((code) => (
            <button
              key={code}
              type="button"
              role="menuitemradio"
              aria-checked={code === current}
              className="sidebar__menu-item"
              onClick={() => {
                void i18n.changeLanguage(code);
                setOpen(false);
              }}
            >
              <span className="sidebar__menu-check">
                <Icon name="check" size={16} />
              </span>
              {t(`language.${code}`)}
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}

/**
 * The console's navigation (PRD §10.1), as in the admin console: three groups, every entry a real link (middle-click
 * and Ctrl+click work), sub-routes highlight their section; a footer with the language and the account with Logout.
 */
export function Sidebar() {
  const {t} = useTranslation('app');
  const viewer = useViewer();
  const [open, setOpen] = useState(false);
  const displayName = nameFromEmail(viewer.email);

  return (
    <>
      <button
        type="button"
        className="sidebar__toggle"
        aria-label={open ? t('nav.closeMenu') : t('nav.openMenu')}
        aria-expanded={open}
        onClick={() => setOpen(!open)}
      >
        <Icon name={open ? 'close' : 'menu'} />
      </button>
      <aside className={`sidebar${open ? ' sidebar--open' : ''}`}>
        <div className="sidebar__header">
          <Link
            to="/ai-experience"
            className="sidebar__brand"
            aria-label={t('brand')}
            onClick={() => setOpen(false)}
          >
            <img
              src="/images/mappi-logo-light.svg"
              alt={t('brand')}
              className="sidebar__logo"
            />
            <span className="sidebar__eyebrow">{t('eyebrow')}</span>
          </Link>
        </div>
        <div className="sidebar__content">
          {viewer.isPlatformAdmin ? (
            <AssumeCustomer placement="selector" />
          ) : null}
          <nav className="sidebar__nav" aria-label={t('nav.label')}>
            {GROUPS.map((group) => (
              <div
                key={group.key}
                className="sidebar__group"
                role="group"
                aria-label={t(`nav.groups.${group.key}`)}
              >
                <p className="sidebar__group-title" aria-hidden="true">
                  {t(`nav.groups.${group.key}`)}
                </p>
                <ul>
                  {group.entries.map((entry) => (
                    <li key={entry.to}>
                      <NavLink
                        to={entry.to}
                        end={entry.to === '/questionnaires'}
                        className={({isActive}) =>
                          [
                            'sidebar__link',
                            isActive && 'sidebar__link--active',
                            entry.highlight && 'sidebar__link--ai',
                          ]
                            .filter(Boolean)
                            .join(' ')
                        }
                        onClick={() => setOpen(false)}
                      >
                        <Icon name={entry.icon} size={17} />
                        <span className="sidebar__link-text">
                          {t(`nav.${entry.key}`)}
                        </span>
                        {entry.highlight ? (
                          <span className="sidebar__dot" aria-hidden="true" />
                        ) : null}
                      </NavLink>
                    </li>
                  ))}
                </ul>
              </div>
            ))}
          </nav>
        </div>
        <div className="sidebar__footer">
          <LanguageMenu />
          <div className="sidebar__account-row">
            <NavLink
              to="/profile"
              className={({isActive}) =>
                `sidebar__account${isActive ? ' sidebar__account--active' : ''}`
              }
              aria-label={t('account.label')}
              onClick={() => setOpen(false)}
            >
              <span className="sidebar__avatar" aria-hidden>
                {initials(displayName) || '?'}
              </span>
              <span className="sidebar__account-text">
                <span className="sidebar__account-name">
                  <span className="sidebar__account-display">
                    {displayName}
                  </span>
                </span>
                <span className="sidebar__account-email">{viewer.email}</span>
              </span>
            </NavLink>
            <Link
              to="/logout"
              className="sidebar__logout"
              aria-label={t('nav.logout')}
              title={t('nav.logout')}
            >
              <Icon name="logout" size={15} />
            </Link>
          </div>
        </div>
      </aside>
    </>
  );
}
