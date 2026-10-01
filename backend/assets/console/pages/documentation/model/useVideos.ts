import {api, type Schema} from '@shared/api';
import type {Language} from '@shared/i18n';
import {useQuery} from '@tanstack/react-query';

export type Video = Schema<'VideoOutput'>;
type VideoList = Schema<'VideoListOutput'>;

export const VIDEOS_QUERY_KEY = ['videos'] as const;

/** GET /videos?language= (PRD §8.12): the documentation videos of the UI language, by order then title. */
export function useVideos(language: Language) {
  return useQuery({
    queryKey: [...VIDEOS_QUERY_KEY, language],
    queryFn: () => api.get<VideoList>('/videos', {query: {language}}),
    select: (data) => data.videos,
  });
}
