import {api, type Schema} from '@shared/api';

export type QuestionnaireDetail = Schema<'QuestionnaireOutput'>;
export type QuestionnaireFlow = Schema<'FlowOutput'>;
export type ChainPrompt = Schema<'PromptOutput'>;
export type FlowBody = Schema<'FlowInput'>;
type SignedUpload = Schema<'SignedUploadOutput'>;

export function questionnaireQueryKey(id: string) {
  return ['questionnaire', id] as const;
}

/** GET /questionnaire/{id}: the questionnaire with the diagnostic tiers merged into on_completed. */
export function fetchQuestionnaire(id: string): Promise<QuestionnaireDetail> {
  return api.get<QuestionnaireDetail>(`/questionnaire/${id}`);
}

/** GET /flow/{questionnaire_id}: slug, states, cta, layout and result_copy. */
export function fetchFlow(questionnaireId: string): Promise<QuestionnaireFlow> {
  return api.get<QuestionnaireFlow>(`/flow/${questionnaireId}`);
}

/** GET /questionnaire/{id}/prompts: a chain's prompts in order, with their text. */
export async function fetchPrompts(id: string): Promise<ChainPrompt[]> {
  const data = await api.get<Schema<'PromptListOutput'>>(
    `/questionnaire/${id}/prompts`,
  );
  return data.prompts;
}

/**
 * Whether anyone answered the questionnaire yet (PRD §10.7: the editor loads the answers to know whether it is
 * locked). Fails open: when the answers cannot be read the editor opens, and a save answering 409
 * QUESTIONNAIRE_ALREADY_ANSWERED locks it then.
 */
export async function fetchHasAnswers(id: string): Promise<boolean> {
  type Sessions = {items?: {questions?: Schema<'QuestionOutput'>[]}[]};
  try {
    const data = await api.get<Sessions>(`/questionnaire/${id}/answers`, {
      query: {status: 'all', limit: 20},
    });
    return (data.items ?? []).some((session) =>
      (session.questions ?? []).some((question) =>
        question.options.some(
          (control) =>
            control.skipped === true ||
            (control.value !== null &&
              control.value !== undefined &&
              control.value !== '' &&
              !(Array.isArray(control.value) && control.value.length === 0)),
        ),
      ),
    );
  } catch {
    return false;
  }
}

/** POST /questionnaire: creates it from a flow; answers its id. */
export async function createQuestionnaire(body: FlowBody): Promise<string> {
  const data = await api.post<Schema<'QuestionnaireIdOutput'>>(
    '/questionnaire',
    body,
  );
  return data.questionnaire_id;
}

/** PUT /questionnaire: saves the edit (the flow keeps its id). */
export async function updateQuestionnaire(
  questionnaireId: string,
  body: FlowBody,
): Promise<void> {
  await api.put<null>('/questionnaire', {
    ...body,
    questionnaire_id: questionnaireId,
  });
}

/** POST /questionnaire/{id}/copy: the new questionnaire. */
export function copyQuestionnaire(id: string): Promise<QuestionnaireDetail> {
  return api.post<QuestionnaireDetail>(`/questionnaire/${id}/copy`);
}

/**
 * Uploads a chain prompt's text to object storage (PRD §10.5 "On save"): POST /signed-urls with upload_type prompt,
 * then the direct PUT. Answers the storage key the flow's prompt state points to.
 */
export async function uploadPromptText(
  customerId: string,
  text: string,
): Promise<string> {
  const contentType = 'text/plain';
  const signed = await api.post<SignedUpload>('/signed-urls', {
    filename: 'prompt.txt',
    content_type: contentType,
    customer_id: customerId,
    upload_type: 'prompt',
  });
  const response = await fetch(signed.url, {
    method: 'PUT',
    headers: {'Content-Type': contentType},
    body: text,
  });
  if (!response.ok) {
    throw new Error(`Prompt upload failed (${response.status})`);
  }
  return signed.key;
}

/** A file question's template once uploaded: its storage key and the name the respondent downloads. */
export type UploadedTemplate = {key: string; filename: string};

/**
 * Uploads a file question's template (any file, up to 20 MB): POST /signed-urls with upload_type template, then the
 * form upload. The key ends with the file's name, so the respondent's download keeps it.
 */
export async function uploadTemplate(
  customerId: string,
  file: File,
): Promise<UploadedTemplate> {
  const signed = await api.post<SignedUpload>('/signed-urls', {
    filename: file.name,
    content_type: file.type || 'application/octet-stream',
    customer_id: customerId,
    upload_type: 'template',
  });
  const form = new FormData();
  for (const [name, value] of Object.entries(signed.fields ?? {})) {
    form.append(name, value);
  }
  form.append('file', file);
  const response = await fetch(signed.url, {method: 'POST', body: form});
  if (!response.ok) {
    throw new Error(`Template upload failed (${response.status})`);
  }
  return {key: signed.key, filename: signed.key.split('/').pop() ?? file.name};
}

/** POST /templates/download-urls: a 15-minute link to a template (the same one the respondent gets). */
export async function templateDownloadUrl(key: string): Promise<string> {
  const signed = await api.post<Schema<'DownloadUrlOutput'>>(
    '/templates/download-urls',
    {key},
  );
  return signed.url;
}
