import {usePlanUsage} from '@console/entities/plan-usage';
import {useViewer} from '@console/entities/viewer';
import {AssumeCustomer} from '@console/widgets/assume-customer';
import {Icon, type IconName, Select} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, NavLink} from 'react-router';

type Entry = {to: string; key: string; icon: IconName; highlight?: boolean};

const GROUPS: Array<{key: string; entries: Entry[]}> = [
  {
    key: 'design',
    entries: [
      {
        to: '/ai-experience',
        key: 'aiExperience',
        icon: 'sparkles',
        highlight: true,
      },
      {
        to: '/questionnaires/new',
        key: 'designExperience',
        icon: 'pencil-ruler',
      },
      {to: '/questionnaires', key: 'questionnaires', icon: 'list'},
      {to: '/customization', key: 'customization', icon: 'palette'},
    ],
  },
  {
    key: 'send',
    entries: [
      {to: '/organizations', key: 'organizations', icon: 'building'},
      {to: '/assignations', key: 'assignations', icon: 'send'},
      {to: '/projects', key: 'projects', icon: 'folder'},
    ],
  },
  {
    key: 'settings',
    entries: [
      {to: '/users', key: 'users', icon: 'users'},
      {to: '/integrations', key: 'integrations', icon: 'plug'},
      {to: '/profile', key: 'profile', icon: 'user'},
      {to: '/documentation', key: 'documentation', icon: 'book'},
    ],
  },
];

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

/**
 * The console's navigation (PRD §10.1): three groups, every entry a real link (middle-click and Ctrl+click work),
 * sub-routes highlight their section; a footer with the language, the account block and Logout.
 */
export function Sidebar() {
  const {t, i18n} = useTranslation('app');
  const viewer = useViewer();
  const {data: usage} = usePlanUsage(!viewer.isAdmin);
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
        <Link
          to="/ai-experience"
          className="sidebar__brand"
          onClick={() => setOpen(false)}
        >
          <span className="sidebar__logo" aria-hidden>
            {t('brand').charAt(0)}
          </span>
          <span>
            <span className="sidebar__eyebrow">{t('eyebrow')}</span>
            <span className="sidebar__name">{t('brand')}</span>
          </span>
        </Link>
        {viewer.isPlatformAdmin ? (
          <AssumeCustomer placement="selector" />
        ) : null}
        <nav className="sidebar__nav" aria-label={t('nav.label')}>
          {GROUPS.map((group) => (
            <div key={group.key} className="sidebar__group">
              <p className="sidebar__group-title">
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
                      <Icon name={entry.icon} />
                      <span>{t(`nav.${entry.key}`)}</span>
                    </NavLink>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </nav>
        <div className="sidebar__footer">
          <label className="sidebar__language">
            <Icon name="globe" size={16} />
            <span className="visually-hidden">{t('language.label')}</span>
            <Select
              aria-label={t('language.label')}
              value={i18n.language}
              onChange={(event) => void i18n.changeLanguage(event.target.value)}
              options={[
                {value: 'es', label: t('language.es')},
                {value: 'en', label: t('language.en')},
              ]}
            />
          </label>
          <Link
            to="/profile"
            className="sidebar__account"
            aria-label={t('account.label')}
          >
            <span className="sidebar__avatar" aria-hidden>
              {initials(displayName) || '?'}
            </span>
            <span className="sidebar__account-text">
              <span className="sidebar__account-name">
                {displayName}
                {usage?.plan ? (
                  <span className="sidebar__plan">{usage.plan.plan_name}</span>
                ) : null}
              </span>
              <span className="sidebar__account-email">{viewer.email}</span>
            </span>
          </Link>
          <Link to="/logout" className="sidebar__link">
            <Icon name="logout" />
            <span>{t('nav.logout')}</span>
          </Link>
        </div>
      </aside>
    </>
  );
}
