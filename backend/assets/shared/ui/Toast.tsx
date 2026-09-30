import {
  createContext,
  type ReactNode,
  useCallback,
  useContext,
  useMemo,
  useState,
} from 'react';
import {useTranslation} from 'react-i18next';
import {ApiError} from '../api/ApiError';
import {errorMessageKey} from '../api/errors';
import {IconButton} from './Button';
import {Icon} from './Icon';

export type ToastTone = 'success' | 'error' | 'warning' | 'info';
type Toast = {id: number; tone: ToastTone; text: string};

type ToastApi = {
  show: (text: string, tone?: ToastTone) => void;
  success: (text: string) => void;
  error: (text: string) => void;
  /** A failed request: amber for plan limits, red otherwise, the backend code's text first (PRD §10.21). */
  apiError: (error: unknown) => void;
};

const ToastContext = createContext<ToastApi | null>(null);

export function ToastProvider({children}: {children: ReactNode}) {
  const {t} = useTranslation('shared');
  const [toasts, setToasts] = useState<Toast[]>([]);

  const dismiss = useCallback((id: number) => {
    setToasts((all) => all.filter((toast) => toast.id !== id));
  }, []);

  const show = useCallback(
    (text: string, tone: ToastTone = 'info') => {
      const id = Date.now() + Math.random();
      setToasts((all) => [...all.slice(-3), {id, tone, text}]);
      setTimeout(() => dismiss(id), tone === 'error' ? 8000 : 5000);
    },
    [dismiss],
  );

  const value = useMemo<ToastApi>(
    () => ({
      show,
      success: (text) => show(text, 'success'),
      error: (text) => show(text, 'error'),
      apiError: (error) => {
        if (error instanceof ApiError) {
          show(
            t(errorMessageKey(error), {defaultValue: error.message}),
            error.isPlanLimit ? 'warning' : 'error',
          );
        } else {
          show(t('errors.INTERNAL_ERROR'), 'error');
        }
      },
    }),
    [show, t],
  );

  return (
    <ToastContext.Provider value={value}>
      {children}
      <div className="toasts" aria-live="polite">
        {toasts.map((toast) => (
          <div
            key={toast.id}
            className={`toast toast--${toast.tone}`}
            role={toast.tone === 'error' ? 'alert' : 'status'}
          >
            <span className="toast__text">{toast.text}</span>
            <IconButton
              size="sm"
              label={t('actions.close')}
              icon={<Icon name="close" size={14} />}
              onClick={() => dismiss(toast.id)}
            />
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  );
}

export function useToast(): ToastApi {
  const api = useContext(ToastContext);
  if (!api) {
    throw new Error('useToast() needs a <ToastProvider> above it.');
  }
  return api;
}
