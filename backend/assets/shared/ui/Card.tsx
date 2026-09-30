import type {HTMLAttributes, ReactNode} from 'react';
import {joinClasses} from '../lib/format';

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

export function PageHeader({
  title,
  subtitle,
  actions,
}: {
  title: ReactNode;
  subtitle?: ReactNode;
  actions?: ReactNode;
}) {
  return (
    <header className="page-header">
      <div>
        <h1 className="page-header__title">{title}</h1>
        {subtitle ? <p className="page-header__subtitle">{subtitle}</p> : null}
      </div>
      {actions ? <div className="page-header__actions">{actions}</div> : null}
    </header>
  );
}
