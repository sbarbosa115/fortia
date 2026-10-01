import {joinClasses} from '@shared/lib';

/** Mappi's glyph on the flat violet, with a four-point spark for the assistant. */
export function AssistantMark({size = 'sm'}: {size?: 'sm' | 'lg'}) {
  return (
    <span
      className={joinClasses('ai-mark', `ai-mark--${size}`)}
      aria-hidden="true"
      data-testid="assistant-mark"
    >
      <svg viewBox="18.4 16.6 50 56" className="ai-mark__glyph" fill="#fff">
        <path d="M47.3,61.6l-16.1,10.3h-5.5s0-7.3,0-7.3l34.7-21.7v4.2s-34.7-21.6-34.7-21.6v-7.3s5.7,0,5.7,0l6.7,4.4,5.8,3.9,3.4,2.1,20.8,12.9v7.1s-20.9,12.9-20.9,12.9l-3.2,2" />
        <path d="M37.1,45.1c0,1.6-.5,3-1.6,4-1.1,1.1-2.4,1.6-4.1,1.6s-2.9-.5-4-1.6c-1.1-1.1-1.7-2.4-1.7-4s.6-3,1.7-4.1c1.1-1,2.5-1.6,4-1.6s3,.5,4.1,1.6c1.1,1,1.6,2.4,1.6,4.1Z" />
      </svg>
      <svg viewBox="0 0 24 24" className="ai-mark__spark">
        <path
          fill="var(--color-primary)"
          d="M12 2c.6 4.7 2.3 6.9 7 7.9-4.7 1-6.4 3.2-7 7.9-.6-4.7-2.3-6.9-7-7.9 4.7-1 6.4-3.2 7-7.9Z"
        />
      </svg>
    </span>
  );
}
