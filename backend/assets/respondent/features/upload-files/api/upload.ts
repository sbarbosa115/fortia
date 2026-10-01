import {api, type Schema} from '@shared/api';

type SignedUpload = Schema<'SignedUploadOutput'>;

/**
 * Uploads one answer file (PRD §9.9): POST /signed-urls, then a direct form upload to the object storage with
 * progress. Resolves with the object key, the value the answer saves.
 */
export async function uploadAnswerFile(
  file: File,
  target: {customerId: string; sessionId: string; questionId: string},
  onProgress: (percent: number) => void,
  token: string | null = null,
): Promise<string> {
  const signed = await api.post<SignedUpload>(
    '/signed-urls',
    {
      filename: file.name,
      content_type: file.type || 'application/octet-stream',
      customer_id: target.customerId,
      upload_type: 'answer_media',
      session_id: target.sessionId,
      question_id: target.questionId,
    },
    {token},
  );
  const form = new FormData();
  for (const [name, value] of Object.entries(signed.fields ?? {})) {
    form.append(name, value);
  }
  form.append('file', file);

  await new Promise<void>((resolve, reject) => {
    const request = new XMLHttpRequest();
    request.open('POST', signed.url);
    request.upload.onprogress = (event) => {
      if (event.lengthComputable) {
        onProgress(Math.round((event.loaded / event.total) * 100));
      }
    };
    request.onload = () =>
      request.status >= 200 && request.status < 300
        ? resolve()
        : reject(new Error(`Upload failed with ${request.status}`));
    request.onerror = () => reject(new Error('Upload failed'));
    request.send(form);
  });
  onProgress(100);
  return signed.key;
}

/**
 * Downloads a file question's template: POST /templates/download-urls signs a 15-minute URL that answers the file as
 * an attachment, so opening it saves the file without leaving the questionnaire.
 */
export async function downloadTemplate(
  key: string,
  token: string | null = null,
): Promise<void> {
  const signed = await api.post<Schema<'DownloadUrlOutput'>>(
    '/templates/download-urls',
    {key},
    {token},
  );
  window.location.assign(signed.url);
}
