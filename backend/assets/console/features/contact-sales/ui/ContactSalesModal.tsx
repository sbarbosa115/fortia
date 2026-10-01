import {Button, Field, Modal, TextInput} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useContactSales} from '../model/useContactSales';

type Props = {
  planId: string;
  planName: string;
  defaultEmail: string;
  onClose: () => void;
};

/** The contact form of a plan that cannot be bought online (PRD §10.15 "Get in touch"). Mount it to open it. */
export function ContactSalesModal({
  planId,
  planName,
  defaultEmail,
  onClose,
}: Props) {
  const {t} = useTranslation('features.contact-sales');
  const {t: ts} = useTranslation('shared');
  const form = useContactSales(planId, defaultEmail);

  if (form.sent) {
    return (
      <Modal
        open
        title={t('sent.title')}
        onClose={onClose}
        footer={
          <Button variant="primary" onClick={onClose}>
            {ts('actions.close')}
          </Button>
        }
      >
        <p>{t('sent.body')}</p>
      </Modal>
    );
  }

  return (
    <Modal
      open
      title={t('title', {plan: planName})}
      onClose={onClose}
      footer={
        <>
          <Button onClick={onClose}>{ts('actions.cancel')}</Button>
          <Button
            variant="primary"
            type="submit"
            form="contact-sales-form"
            loading={form.sending}
          >
            {t('submit')}
          </Button>
        </>
      }
    >
      <form
        id="contact-sales-form"
        className="stack"
        noValidate
        onSubmit={form.submit}
      >
        <p className="muted">{t('intro')}</p>
        <Field
          label={t('email')}
          required
          error={form.errors.email ? t('errors.email') : null}
        >
          <TextInput
            type="email"
            autoComplete="email"
            value={form.email}
            onChange={(event) => form.setEmail(event.target.value)}
          />
        </Field>
        <Field
          label={t('phone')}
          required
          error={form.errors.phone ? t('errors.phone') : null}
        >
          <TextInput
            type="tel"
            autoComplete="tel"
            maxLength={50}
            value={form.phone}
            onChange={(event) => form.setPhone(event.target.value)}
          />
        </Field>
      </form>
    </Modal>
  );
}
