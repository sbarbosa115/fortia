import {useDocumentTitle} from '@shared/lib';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  ErrorState,
  Field,
  Icon,
  LoadingState,
  PageHeader,
  Select,
  TextInput,
} from '@shared/ui';
import {useState} from 'react';
import {useTranslation} from 'react-i18next';
import {FONTS, isBrandColor} from '../model/brand';
import {useCustomization} from '../model/useCustomization';
import {BrandPreview} from './BrandPreview';
import './customization.css';

const FONT_OPTIONS = FONTS.map((font) => ({value: font, label: font}));

/** /customization (PRD §10.13): the account's brand for the respondent screens, with a live preview. */
export function CustomizationPage() {
  const {t} = useTranslation('pages.customization');
  const state = useCustomization();
  const [brokenLogo, setBrokenLogo] = useState<string | null>(null);
  useDocumentTitle(`Mappi - ${t('title')}`);

  if (state.loading) {
    return <LoadingState />;
  }
  if (state.loadError) {
    return (
      <Card>
        <ErrorState error={state.loadError} onRetry={state.retry} />
      </Card>
    );
  }

  const {form, errors} = state;
  const fieldError = (field: keyof typeof errors) =>
    state.showErrors && errors[field] ? t(`errors.${errors[field]}`) : null;
  const logo = form.logoUrl.trim();
  const disabled = state.readOnly || state.saving;

  return (
    <form
      className="customization"
      noValidate
      onSubmit={(event) => {
        event.preventDefault();
        state.submit();
      }}
    >
      <PageHeader
        title={t('title')}
        subtitle={t('subtitle')}
        actions={
          <>
            <Button
              type="button"
              icon={<Icon name="refresh" size={16} />}
              disabledReason={state.readOnly ? state.saveDisabledReason : null}
              disabled={state.saving}
              onClick={state.reset}
            >
              {t('reset')}
            </Button>
            <Button
              type="submit"
              variant="primary"
              loading={state.saving}
              disabledReason={state.saveDisabledReason}
              icon={<Icon name="check" size={16} />}
            >
              {t('save')}
            </Button>
          </>
        }
      />
      {state.saving ? (
        <p className="customization__progress" role="status">
          {t(`stages.${state.stage ?? 'queued'}`, {
            defaultValue: t('stages.queued'),
          })}
        </p>
      ) : null}
      <div className="customization__grid">
        <Card>
          <CardHeader title={t('brand')} />
          <CardBody>
            <div className="stack">
              <Field
                label={t('website')}
                hint={t('websiteHint')}
                error={fieldError('website')}
              >
                <TextInput
                  type="url"
                  value={form.website}
                  placeholder={t('websitePlaceholder')}
                  spellCheck={false}
                  autoCapitalize="none"
                  disabled={disabled}
                  onChange={(event) =>
                    state.setField({website: event.target.value})
                  }
                />
              </Field>
              {state.readsWebsite ? (
                <p className="customization__notice" role="note">
                  {form.website.trim() === ''
                    ? t('websiteRemoved')
                    : t('websiteChanged')}
                </p>
              ) : null}
              <div className="customization__logo-row">
                <Field
                  label={t('logo')}
                  hint={t('logoHint')}
                  error={fieldError('logoUrl')}
                  className="customization__grow"
                >
                  <TextInput
                    type="url"
                    value={form.logoUrl}
                    placeholder={t('logoPlaceholder')}
                    spellCheck={false}
                    autoCapitalize="none"
                    disabled={disabled}
                    onChange={(event) =>
                      state.setField({logoUrl: event.target.value})
                    }
                  />
                </Field>
                <div className="customization__logo-preview">
                  {logo !== '' && !errors.logoUrl && brokenLogo !== logo ? (
                    <img
                      src={logo}
                      alt={t('logoPreview')}
                      onError={() => setBrokenLogo(logo)}
                    />
                  ) : (
                    <span>
                      {brokenLogo === logo && logo !== ''
                        ? t('logoBroken')
                        : t('noLogo')}
                    </span>
                  )}
                </div>
              </div>
              <Field label={t('font')}>
                <Select
                  value={form.font}
                  options={FONT_OPTIONS}
                  disabled={disabled}
                  onChange={(event) =>
                    state.setField({font: event.target.value})
                  }
                />
              </Field>
              <Field
                label={t('brandColor')}
                hint={t('brandColorHint')}
                error={fieldError('brandColor')}
              >
                <TextInput
                  value={form.brandColor}
                  maxLength={7}
                  spellCheck={false}
                  autoCapitalize="none"
                  disabled={disabled}
                  onChange={(event) =>
                    state.setField({brandColor: event.target.value})
                  }
                />
              </Field>
              <label className="customization__swatch">
                <input
                  type="color"
                  value={
                    isBrandColor(form.brandColor)
                      ? form.brandColor.toLowerCase()
                      : '#000000'
                  }
                  disabled={disabled}
                  onChange={(event) =>
                    state.setField({brandColor: event.target.value})
                  }
                />
                <span>{t('pickColor')}</span>
              </label>
            </div>
          </CardBody>
        </Card>
        <Card className="customization__preview">
          <CardHeader title={t('preview.title')} />
          <CardBody>
            <BrandPreview theme={state.theme} />
            <p className="customization__preview-note">{t('preview.note')}</p>
          </CardBody>
        </Card>
      </div>
    </form>
  );
}
