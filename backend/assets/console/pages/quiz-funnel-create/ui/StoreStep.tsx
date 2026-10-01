import {ShopifyCard} from '@console/features/shopify-connection';
import {
  Card,
  CardBody,
  CardHeader,
  ChoiceCards,
  Field,
  Select,
  TextInput,
} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {PRODUCT_LIMITS, type Source} from '../model/quizFunnel';
import type {QuizFunnelState} from '../model/useQuizFunnel';

/** Step 1 (PRD §10.5): where the catalog comes from — the store's website, or the e-commerce platform. */
export function StoreStep({state}: {state: QuizFunnelState}) {
  const {t} = useTranslation('pages.quiz-funnel-create');
  return (
    <div className="quiz-funnel__step">
      <ChoiceCards<Source>
        label={t('store.sourceLabel')}
        value={state.source}
        onChange={state.setSource}
        choices={[
          {
            value: 'website',
            title: t('store.website.title'),
            body: t('store.website.body'),
          },
          {
            value: 'shopify',
            title: t('store.shopify.title'),
            body: t('store.shopify.body'),
          },
        ]}
      />
      {state.source === 'website' ? (
        <Card>
          <CardHeader title={t('store.website.title')} />
          <CardBody>
            <div className="quiz-funnel__store-fields">
              <Field
                label={t('store.url')}
                hint={t('store.urlHint')}
                required
                error={state.storeError ? t('errors.invalidUrl') : null}
                className="quiz-funnel__grow"
              >
                <TextInput
                  type="url"
                  inputMode="url"
                  value={state.storeUrl}
                  maxLength={2048}
                  spellCheck={false}
                  autoCapitalize="none"
                  placeholder={t('store.urlPlaceholder')}
                  onChange={(event) => state.setStoreUrl(event.target.value)}
                  onBlur={state.touchStore}
                />
              </Field>
              <Field label={t('store.limit')}>
                <Select
                  value={String(state.limit)}
                  options={PRODUCT_LIMITS.map((n) => ({
                    value: String(n),
                    label: t('store.limitOption', {count: n}),
                  }))}
                  onChange={(event) =>
                    state.setLimit(Number(event.target.value))
                  }
                />
              </Field>
            </div>
          </CardBody>
        </Card>
      ) : (
        <ShopifyCard state={state.shopify} />
      )}
    </div>
  );
}
