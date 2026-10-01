import {useEffect, useState} from 'react';
import {useTranslation} from 'react-i18next';
import {TESTIMONIAL_INTERVAL_MS, TESTIMONIALS} from '../config/links';

/**
 * The login's brand panel (PRD §10.2): the promise, three stats and three testimonials that rotate every 9 s. The
 * rotation pauses while the pointer or the keyboard is on it, and a dot picks one (WCAG 2.2.2).
 */
export function BrandPanel() {
  const {t} = useTranslation('pages.login');
  const [index, setIndex] = useState(0);
  const [paused, setPaused] = useState(false);

  useEffect(() => {
    if (paused) {
      return undefined;
    }
    const timer = setInterval(
      () => setIndex((current) => (current + 1) % TESTIMONIALS.length),
      TESTIMONIAL_INTERVAL_MS,
    );
    return () => clearInterval(timer);
  }, [paused]);

  const key = TESTIMONIALS[index] ?? 'one';
  const stats = ['conversion', 'guided', 'first'] as const;

  return (
    <aside
      className="login__panel-brand"
      aria-label={t('brand.label')}
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocus={() => setPaused(true)}
      onBlur={() => setPaused(false)}
    >
      <p className="brand__headline serif-heading">{t('brand.headline')}</p>
      <dl className="brand__stats">
        {stats.map((stat) => (
          <div key={stat} className="brand__stat">
            <dt className="brand__stat-value">
              {t(`brand.stats.${stat}.value`)}
            </dt>
            <dd className="brand__stat-label">
              {t(`brand.stats.${stat}.label`)}
            </dd>
          </div>
        ))}
      </dl>
      <figure className="brand__testimonial" key={key}>
        <blockquote>{t(`brand.testimonials.${key}.quote`)}</blockquote>
        <figcaption>
          <strong>{t(`brand.testimonials.${key}.name`)}</strong>
          <span>{t(`brand.testimonials.${key}.role`)}</span>
        </figcaption>
      </figure>
      <div className="brand__dots">
        {TESTIMONIALS.map((item, position) => (
          <button
            key={item}
            type="button"
            className="brand__dot"
            aria-label={t('brand.show', {number: position + 1})}
            aria-pressed={position === index}
            onClick={() => setIndex(position)}
          />
        ))}
      </div>
    </aside>
  );
}
