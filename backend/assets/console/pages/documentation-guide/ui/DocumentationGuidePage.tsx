import {
  type Guide,
  type GuideScreenshot,
  type GuideSection,
  guideLanguage,
  guideNeighbours,
  guidesFor,
  readingMinutes,
  screenshotUrl,
} from '@console/entities/guide';
import type {Language} from '@shared/i18n';
import {useDocumentTitle} from '@shared/lib';
import {Badge, Card, EmptyState, Icon} from '@shared/ui';
import {type MouseEvent, useEffect, useMemo, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {Link, useParams} from 'react-router';
import './guide.css';

/**
 * /documentation/guides/:guideId (PRD §10.18): one guide in the UI language, with its table of contents, its
 * screenshots in that language and links to the previous and next guide.
 */
export function DocumentationGuidePage() {
  const {t, i18n} = useTranslation('pages.documentation-guide');
  const {guideId = ''} = useParams();
  const language = guideLanguage(i18n.language);
  const guides = useMemo(() => guidesFor(language), [language]);
  const guide = guides.find((candidate) => candidate.id === guideId) ?? null;
  useDocumentTitle(`Mappi - ${guide?.title ?? t('title')}`);

  useEffect(() => {
    window.scrollTo?.({top: 0});
  }, [guideId]);

  if (guide === null) {
    return (
      <Card>
        <EmptyState
          title={t('notFound')}
          body={t('notFoundBody')}
          action={
            <Link className="btn btn--primary" to="/documentation">
              {t('backToDocs')}
            </Link>
          }
        />
      </Card>
    );
  }

  const {previous, next} = guideNeighbours(guides, guide.id);
  return (
    <div className="guide">
      <nav className="guide__crumbs" aria-label={t('breadcrumb')}>
        <Link to="/documentation">{t('documentation')}</Link>
        <Icon name="chevron-right" size={14} />
        <span aria-current="page">{guide.title}</span>
      </nav>
      <GuideHeader guide={guide} />
      <div className="guide__layout">
        <article className="guide__body" aria-labelledby="guide-title">
          {guide.sections.map((section) => (
            <Section key={section.id} section={section} language={language} />
          ))}
        </article>
        <TableOfContents sections={guide.sections} />
      </div>
      <nav className="guide__pager" aria-label={t('pager')}>
        {previous ? (
          <PagerLink
            guide={previous}
            label={t('previous')}
            direction="previous"
          />
        ) : (
          <span />
        )}
        {next ? (
          <PagerLink guide={next} label={t('next')} direction="next" />
        ) : null}
      </nav>
    </div>
  );
}

function GuideHeader({guide}: {guide: Guide}) {
  const {t: tGuide} = useTranslation('entities.guide');
  return (
    <header className="guide__header">
      <div className="guide__meta">
        <Badge tone="accent">{tGuide(`topics.${guide.topic}`)}</Badge>
        <span className="guide__time">
          {tGuide('readingTime', {count: readingMinutes(guide)})}
        </span>
      </div>
      <h1 className="guide__title serif-heading" id="guide-title">
        {guide.title}
      </h1>
      <p className="guide__summary">{guide.summary}</p>
    </header>
  );
}

function Section({
  section,
  language,
}: {
  section: GuideSection;
  language: Language;
}) {
  const {t} = useTranslation('pages.documentation-guide');
  return (
    <section className="guide__section" aria-labelledby={section.id}>
      <h2 className="guide__heading" id={section.id}>
        {section.heading}
      </h2>
      {section.paragraphs.map((paragraph) => (
        <p key={paragraph}>{paragraph}</p>
      ))}
      {section.steps ? (
        <ol className="guide__steps">
          {section.steps.map((step) => (
            <li key={step}>{step}</li>
          ))}
        </ol>
      ) : null}
      {section.screenshot ? (
        <Screenshot screenshot={section.screenshot} language={language} />
      ) : null}
      {section.tip ? (
        <aside className="guide__tip" aria-label={t('tip')}>
          <Icon name="info" size={18} />
          <p>
            <strong>{t('tipLabel')}</strong>
            {section.tip}
          </p>
        </aside>
      ) : null}
    </section>
  );
}

/** The screen in the reader's language; hidden when the image is missing so a guide never shows a broken image. */
function Screenshot({
  screenshot,
  language,
}: {
  screenshot: GuideScreenshot;
  language: Language;
}) {
  const src = screenshotUrl(language, screenshot.name);
  const [failed, setFailed] = useState<string | null>(null);
  if (failed === src) {
    return null;
  }
  return (
    <figure className="guide__figure">
      <img
        src={src}
        alt={screenshot.alt}
        loading="lazy"
        onError={() => setFailed(src)}
      />
      <figcaption>{screenshot.alt}</figcaption>
    </figure>
  );
}

function TableOfContents({sections}: {sections: GuideSection[]}) {
  const {t} = useTranslation('pages.documentation-guide');
  const jump = (event: MouseEvent<HTMLAnchorElement>, id: string) => {
    const target = document.getElementById(id);
    if (target) {
      event.preventDefault();
      target.scrollIntoView?.({behavior: 'smooth', block: 'start'});
      target.setAttribute('tabindex', '-1');
      target.focus({preventScroll: true});
    }
  };
  return (
    <nav className="guide__toc" aria-label={t('contents')}>
      <p className="guide__toc-title">{t('contents')}</p>
      <ol>
        {sections.map((section) => (
          <li key={section.id}>
            <a
              href={`#${section.id}`}
              onClick={(event) => jump(event, section.id)}
            >
              {section.heading}
            </a>
          </li>
        ))}
      </ol>
    </nav>
  );
}

function PagerLink({
  guide,
  label,
  direction,
}: {
  guide: Guide;
  label: string;
  direction: 'previous' | 'next';
}) {
  return (
    <Link
      className={`guide__pager-link guide__pager-link--${direction}`}
      to={`/documentation/guides/${guide.id}`}
      rel={direction === 'previous' ? 'prev' : 'next'}
    >
      <span className="guide__pager-label">
        {direction === 'previous' ? (
          <Icon name="chevron-left" size={16} />
        ) : null}
        {label}
        {direction === 'next' ? <Icon name="chevron-right" size={16} /> : null}
      </span>
      <span className="guide__pager-title">{guide.title}</span>
    </Link>
  );
}
