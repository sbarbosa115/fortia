import {
  createContext,
  type HTMLAttributes,
  type ReactNode,
  useContext,
} from 'react';
import {joinClasses} from '../lib/format';
import {Icon} from './Icon';

export function Card({
  className,
  children,
  ...rest
}: HTMLAttributes<HTMLDivElement>) {
  return (
    <div className={joinClasses('card', className)} {...rest}>
      {children}
    </div>
  );
}

export function CardHeader({
  title,
  actions,
}: {
  title: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <div className="card__header">
      <h2 className="card__title">{title}</h2>
      {actions ? <div className="row">{actions}</div> : null}
    </div>
  );
}

export function CardBody({children}: {children: ReactNode}) {
  return <div className="card__body">{children}</div>;
}

/** A breadcrumb link, drawn by the app (it knows its router): see PageCrumbsProvider. */
export type CrumbLink = (props: {
  to: string;
  className: string;
  children: ReactNode;
}) => ReactNode;

type Crumbs = {
  rootLabel: string;
  rootTo: string;
  backLabel: string;
  link: CrumbLink;
};

const CrumbsContext = createContext<Crumbs | null>(null);

/** Gives every PageHeader below its breadcrumb root ("Workspace") and the app's link. */
export function PageCrumbsProvider({
  children,
  ...crumbs
}: Crumbs & {children: ReactNode}) {
  return (
    <CrumbsContext.Provider value={crumbs}>{children}</CrumbsContext.Provider>
  );
}

/**
 * The page heading, as in the admin console: "Workspace / [parent /] page" crumbs (when the app provides them), the
 * title, the subtitle and the actions, over a hairline.
 */
export function PageHeader({
  title,
  subtitle,
  actions,
  breadcrumb,
  parent,
  onBack,
}: {
  title: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
  /** The last crumb; defaults to the title when it is text. */
  breadcrumb?: string;
  /** The crumb between the root and this page (a list the page belongs to). */
  parent?: {label: string; to?: string};
  onBack?: () => void;
}) {
  const crumbs = useContext(CrumbsContext);
  const current = breadcrumb ?? (typeof title === 'string' ? title : null);
  return (
    <header className="page-header">
      <div className="page-header__text">
        {crumbs ? (
          <nav className="page-header__crumbs" aria-label="Breadcrumb">
            {onBack ? (
              <button
                type="button"
                className="page-header__back"
                aria-label={crumbs.backLabel}
                onClick={onBack}
              >
                <Icon name="chevron-left" size={14} />
              </button>
            ) : null}
            {crumbs.link({
              to: crumbs.rootTo,
              className: 'page-header__crumb',
              children: crumbs.rootLabel,
            })}
            {parent ? (
              <>
                <span aria-hidden="true">/</span>
                {parent.to ? (
                  crumbs.link({
                    to: parent.to,
                    className: 'page-header__crumb',
                    children: parent.label,
                  })
                ) : (
                  <span>{parent.label}</span>
                )}
              </>
            ) : null}
            <span aria-hidden="true">/</span>
            <span className="page-header__current" aria-current="page">
              {current}
            </span>
          </nav>
        ) : null}
        <h1 className="page-header__title">{title}</h1>
        {subtitle ? <p className="page-header__subtitle">{subtitle}</p> : null}
      </div>
      {actions ? <div className="page-header__actions">{actions}</div> : null}
    </header>
  );
}
