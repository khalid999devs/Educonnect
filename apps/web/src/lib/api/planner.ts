import { z } from "zod";

import { apiFetch, envelopeData } from "./http";

const taskVersionSchema = z.object({
  task: z.object({ id: z.string(), version: z.number().int().min(1) }),
});

/**
 * Completing from the dashboard: the aggregate deliberately omits versions,
 * so read the task's current version first, then apply the status change
 * with optimistic concurrency.
 */
export async function completeTask(taskId: string): Promise<void> {
  const shown = taskVersionSchema.parse(
    envelopeData(await apiFetch(`/api/v1/tasks/${taskId}`)),
  );

  await apiFetch(`/api/v1/tasks/${taskId}/status`, {
    method: "PUT",
    body: { expected_version: shown.task.version, status: "completed" },
  });
}
