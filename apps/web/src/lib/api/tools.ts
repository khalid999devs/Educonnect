import { apiFetch } from "./http";

/** Tool preference mutations (mutually exclusive per contract: saving clears
 * any dismissal and vice versa). Shared by the dashboard and guidance pages. */

export async function saveTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/saved`, { method: "PUT" });
}

export async function unsaveTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/saved`, { method: "DELETE" });
}

export async function dismissTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/dismissed`, { method: "PUT" });
}

export async function undismissTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/dismissed`, { method: "DELETE" });
}
