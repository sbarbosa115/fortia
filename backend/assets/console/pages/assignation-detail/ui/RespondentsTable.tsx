import type {Respondent} from '@console/entities/assignation';
import {formatDateTime} from '@shared/lib';
import {Badge, Icon, IconButton, type Tone} from '@shared/ui';
import {Fragment, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link} from 'react-router';

const TONES: Record<Respondent['status'], Tone> = {
  pending: 'neutral',
  in_progress: 'accent',
  completed: 'success',
};

/** A respondent's status pill; with a chain, how many stages are done. */
export function RespondentStatus({row}: {row: Respondent}) {
  const {t} = useTranslation('pages.assignation-detail');
  return (
    <span className="asg-detail__status">
      <Badge tone={TONES[row.status]}>
        {t(`respondents.status.${row.status}`)}
      </Badge>
      {row.total_stages > 1 ? (
        <span className="muted">
          {t('respondents.stages', {
            done: row.completed_stages,
            total: row.total_stages,
          })}
        </span>
      ) : null}
    </span>
  );
}

/**
 * Respondents (PRD §10.11 default type): Name, Email, Attempts, Status and "View answers"; each member's attempt
 * history opens under their row.
 */
export function RespondentsTable({
  rows,
  questionnaireId,
  assignationId,
  caption,
}: {
  rows: Respondent[];
  questionnaireId: string;
  /** The answer detail's back link returns here (PRD §10.8). */
  assignationId: string;
  caption: string;
}) {
  const {t, i18n} = useTranslation('pages.assignation-detail');
  const [open, setOpen] = useState<Set<string>>(() => new Set());
  const toggle = (id: string) =>
    setOpen((current) => {
      const next = new Set(current);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });

  return (
    <div className="table-wrap">
      <table className="table">
        <caption className="visually-hidden">{caption}</caption>
        <thead>
          <tr>
            <th scope="col">{t('respondents.columns.name')}</th>
            <th scope="col">{t('respondents.columns.email')}</th>
            <th scope="col">{t('respondents.columns.attempts')}</th>
            <th scope="col">{t('respondents.columns.status')}</th>
            <th scope="col">
              <span className="visually-hidden">
                {t('respondents.columns.actions')}
              </span>
            </th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => {
            const expanded = open.has(row.organization_user_id);
            const panel = `history-${row.organization_user_id}`;
            const name = row.organization_user_name;
            return (
              <Fragment key={row.organization_user_id}>
                <tr>
                  <td>
                    <strong>{name}</strong>
                  </td>
                  <td>
                    {row.organization_user_email ?? (
                      <span className="muted">{t('respondents.noEmail')}</span>
                    )}
                  </td>
                  <td>
                    <span className="asg-detail__attempts">
                      {row.attempts}
                      {row.attempts > 0 ? (
                        <IconButton
                          size="sm"
                          icon={
                            <Icon
                              name={expanded ? 'chevron-up' : 'chevron-down'}
                            />
                          }
                          label={t(
                            expanded
                              ? 'respondents.hideHistory'
                              : 'respondents.showHistory',
                            {name},
                          )}
                          aria-expanded={expanded}
                          aria-controls={panel}
                          onClick={() => toggle(row.organization_user_id)}
                        />
                      ) : null}
                    </span>
                  </td>
                  <td>
                    <RespondentStatus row={row} />
                  </td>
                  <td>
                    <div className="table__actions">
                      {row.session_id ? (
                        <Link
                          className="btn btn--ghost btn--sm"
                          to={`/questionnaires/${questionnaireId}/answers/${row.session_id}?from=/assignations/${assignationId}`}
                          aria-label={t('respondents.viewAnswersOf', {name})}
                        >
                          {t('respondents.viewAnswers')}
                        </Link>
                      ) : null}
                    </div>
                  </td>
                </tr>
                {expanded ? (
                  <tr id={panel} className="asg-detail__history">
                    <td colSpan={5}>
                      <ul aria-label={t('respondents.history', {name})}>
                        {row.attempts_detail.map((attempt) => (
                          <li key={attempt.session_id}>
                            <strong>
                              {t('respondents.attempt', {n: attempt.number})}
                            </strong>
                            <span className="muted">
                              {t('respondents.started', {
                                date: formatDateTime(
                                  attempt.started_at,
                                  i18n.language,
                                ),
                              })}
                            </span>
                            {attempt.ended_at ? (
                              <span className="muted">
                                {t('respondents.ended', {
                                  date: formatDateTime(
                                    attempt.ended_at,
                                    i18n.language,
                                  ),
                                })}
                              </span>
                            ) : null}
                            <Link
                              to={`/questionnaires/${questionnaireId}/answers/${attempt.session_id}?from=/assignations/${assignationId}`}
                            >
                              {t('respondents.viewAnswers')}
                            </Link>
                          </li>
                        ))}
                      </ul>
                    </td>
                  </tr>
                ) : null}
              </Fragment>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
