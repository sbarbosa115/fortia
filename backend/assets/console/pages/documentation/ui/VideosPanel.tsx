import {guideLanguage} from '@console/entities/guide';
import {
  Badge,
  Button,
  Card,
  EmptyState,
  ErrorState,
  Icon,
  LoadingState,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {embedUrl} from '../lib/youtube';
import {useVideos, type Video} from '../model/useVideos';

/** The videos of the UI language (GET /videos?language=), embedded in privacy-enhanced mode (PRD §10.18). */
export function VideosPanel({onShowGuides}: {onShowGuides: () => void}) {
  const {t, i18n} = useTranslation('pages.documentation');
  const videos = useVideos(guideLanguage(i18n.language));

  if (videos.isPending) {
    return <LoadingState label={t('videos.loading')} />;
  }
  if (videos.isError) {
    return (
      <Card>
        <ErrorState
          error={videos.error}
          onRetry={() => void videos.refetch()}
        />
      </Card>
    );
  }
  if (videos.data.length === 0) {
    return (
      <Card>
        <EmptyState
          title={t('videos.empty')}
          body={t('videos.emptyBody')}
          action={
            <Button variant="primary" onClick={onShowGuides}>
              {t('videos.readGuides')}
            </Button>
          }
        />
      </Card>
    );
  }
  return (
    <ul className="docs-videos">
      {videos.data.map((video) => (
        <li key={video.id}>
          <VideoCard video={video} />
        </li>
      ))}
    </ul>
  );
}

function VideoCard({video}: {video: Video}) {
  const {t} = useTranslation('pages.documentation');
  const src = embedUrl(video.url);
  const titleId = `video-${video.id}`;
  return (
    <Card className="docs-video">
      <article aria-labelledby={titleId}>
        <div className="docs-video__player">
          {src ? (
            <iframe
              src={src}
              title={t('videos.player', {title: video.title})}
              loading="lazy"
              referrerPolicy="strict-origin-when-cross-origin"
              allow="accelerometer; encrypted-media; gyroscope; picture-in-picture"
              allowFullScreen
            />
          ) : (
            <p className="docs-video__unavailable">{t('videos.unavailable')}</p>
          )}
        </div>
        <div className="docs-video__body">
          <div className="docs-card__meta">
            {video.category ? <Badge>{video.category}</Badge> : null}
            {video.duration_minutes > 0 ? (
              <span className="docs-card__time">
                {t('videos.minutes', {count: video.duration_minutes})}
              </span>
            ) : null}
          </div>
          <h2 className="docs-card__title" id={titleId}>
            {video.title}
          </h2>
          {video.description ? (
            <p className="docs-card__summary">{video.description}</p>
          ) : null}
          <a
            className="docs-card__read"
            href={video.url}
            target="_blank"
            rel="noopener noreferrer"
          >
            {t('videos.openOnYoutube')}
            <Icon name="external" size={16} />
          </a>
        </div>
      </article>
    </Card>
  );
}
