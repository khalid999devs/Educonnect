import { apiFetch } from "./http";

export async function saveTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/saved`, { method: "PUT" });
}

export async function unsaveTool(toolId: string): Promise<void> {
  await apiFetch(`/api/v1/tools/${toolId}/saved`, { method: "DELETE" });
}
