import {Button, Icon, useToast} from '@shared/ui';
import {useTranslation} from 'react-i18next';

/** Copies a text and says so in a toast. */
export function useCopy(): (text: string) => void {
  const {t} = useTranslation('pages.integrations');
  const toast = useToast();
  return (text: string) => {
    const done = navigator.clipboard?.writeText(text);
    if (!done) {
      toast.error(t('notCopied'));
      return;
    }
    done.then(
      () => toast.success(t('copied')),
      () => toast.error(t('notCopied')),
    );
  };
}

/** A block of code with its Copy button (the curl examples, the sample payload). */
export function CodeBlock({code, label}: {code: string; label: string}) {
  const {t} = useTranslation('pages.integrations');
  const copy = useCopy();
  return (
    <div className="int-code">
      <div className="int-code__bar">
        <span className="int-code__label">{label}</span>
        <Button
          size="sm"
          variant="ghost"
          icon={<Icon name="copy" size={16} />}
          aria-label={`${t('copy')} — ${label}`}
          onClick={() => copy(code)}
        >
          {t('copy')}
        </Button>
      </div>
      <pre className="int-code__body">
        <code>{code}</code>
      </pre>
    </div>
  );
}
