import {Checkbox, Field, Select} from '@shared/ui';
import {useTranslation} from 'react-i18next';
import {useEditorContext} from '../model/EditorContext';
import {
  type DraftQuestion,
  TEXT_CHARSETS,
  type TextCharset,
  type TextFormat,
} from '../model/types';

const PRESETS: TextFormat['preset'][] = ['free', 'rfc', 'nit', 'phone'];

/**
 * A text answer's data type (PRD §10.5): "Free (choose characters)" with All / Letters / Numbers / Symbols (at
 * least one checked), or the RFC, NIT and phone presets.
 */
export function TextFormatFields({question}: {question: DraftQuestion}) {
  const {t} = useTranslation('pages.questionnaire-editor');
  const editor = useEditorContext();
  const format = question.textFormat;
  const set = (textFormat: TextFormat) =>
    editor.updateQuestion(question.key, {textFormat});
  const toggle = (charset: TextCharset, on: boolean) => {
    const charsets = on
      ? [...format.charsets, charset]
      : format.charsets.filter((c) => c !== charset);
    if (charsets.length === 0) {
      set({...format, all: true, charsets: []});
      return;
    }
    set({
      ...format,
      all: charsets.length === TEXT_CHARSETS.length,
      charsets: charsets.length === TEXT_CHARSETS.length ? [] : charsets,
    });
  };
  return (
    <div className="stack">
      <Field label={t('questions.dataTypeLabel')}>
        <Select
          value={format.preset}
          onChange={(e) =>
            set({
              preset: e.target.value as TextFormat['preset'],
              all: true,
              charsets: [],
            })
          }
          options={PRESETS.map((preset) => ({
            value: preset,
            label: t(`questions.dataTypes.${preset}`),
          }))}
        />
      </Field>
      {format.preset === 'free' ? (
        <div className="row" role="group" aria-label={t('questions.dataTypes.free')}>
          <Checkbox
            label={t('questions.charsets.all')}
            checked={format.all}
            onChange={(e) =>
              set({...format, all: e.target.checked, charsets: e.target.checked ? [] : ['letters']})
            }
          />
          {TEXT_CHARSETS.map((charset) => (
            <Checkbox
              key={charset}
              label={t(`questions.charsets.${charset}`)}
              checked={format.all || format.charsets.includes(charset)}
              onChange={(e) =>
                format.all
                  ? set({
                      ...format,
                      all: false,
                      charsets: TEXT_CHARSETS.filter((c) => c !== charset),
                    })
                  : toggle(charset, e.target.checked)
              }
            />
          ))}
        </div>
      ) : null}
    </div>
  );
}
