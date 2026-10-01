import {DAY_MS, readStored, removeStored, writeStored} from '@shared/lib';

/**
 * Which questionnaire of the assignation the respondent is on (PRD §9.14 `assignation_progress:{assignationId}`):
 * the root, or a later stage of its flow.
 */
export type AssignationProgress = {
  assignationId: string;
  questionnaireId: string;
  flowId?: string | null;
  updatedAt: number;
};

const progressKey = (assignationId: string) =>
  `assignation_progress:${assignationId}`;

export function readAssignationProgress(
  assignationId: string,
): AssignationProgress | null {
  const progress = readStored<AssignationProgress>(progressKey(assignationId));
  return progress?.assignationId === assignationId &&
    typeof progress.questionnaireId === 'string'
    ? progress
    : null;
}

export function writeAssignationProgress(
  progress: Omit<AssignationProgress, 'updatedAt'>,
): void {
  writeStored<AssignationProgress>(
    progressKey(progress.assignationId),
    {...progress, updatedAt: Date.now()},
    DAY_MS,
  );
}

export function clearAssignationProgress(assignationId: string): void {
  removeStored(progressKey(assignationId));
}
