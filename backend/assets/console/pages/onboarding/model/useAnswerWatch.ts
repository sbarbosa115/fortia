import {useEffect, useState} from 'react';
import {hasAnswer} from '../api/onboarding';
import {pollDelay, PROCESSING_MS} from './progress';

export type WatchStatus = 'waiting' | 'processing' | 'ready';

/**
 * Step 6 (PRD §10.3): polls the questionnaire's answers, first after 5 s and then every 4 s, until one appears;
 * then "processing" for 3 s and "ready". A failed poll just waits for the next one.
 */
export function useAnswerWatch(
  questionnaireId: string | null,
  active: boolean,
): WatchStatus {
  const [status, setStatus] = useState<WatchStatus>('waiting');

  useEffect(() => {
    if (!active || !questionnaireId || status !== 'waiting') {
      return;
    }
    let cancelled = false;
    let timer: ReturnType<typeof setTimeout>;
    const schedule = (attempt: number) => {
      timer = setTimeout(() => {
        hasAnswer(questionnaireId)
          .catch(() => false)
          .then((found) => {
            if (cancelled) {
              return;
            }
            if (found) {
              setStatus('processing');
            } else {
              schedule(attempt + 1);
            }
          });
      }, pollDelay(attempt));
    };
    schedule(0);
    return () => {
      cancelled = true;
      clearTimeout(timer);
    };
  }, [active, questionnaireId, status]);

  useEffect(() => {
    if (status !== 'processing') {
      return;
    }
    const timer = setTimeout(() => setStatus('ready'), PROCESSING_MS);
    return () => clearTimeout(timer);
  }, [status]);

  return status;
}
