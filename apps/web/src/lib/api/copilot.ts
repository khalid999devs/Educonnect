import { z } from "zod";

import { apiFetch, envelopeData } from "./http";
import { browserTimezone } from "@/components/dashboard/format";

const availabilitySchema = z.object({
  copilot: z.object({ enabled: z.boolean(), model: z.string() }),
});

const replySchema = z.object({
  copilot: z.object({
    reply: z.string(),
    model: z.string(),
    disclaimer: z.string(),
  }),
});

export type CopilotTurn = { role: "user" | "assistant"; content: string };

export async function getCopilotAvailability(): Promise<{
  enabled: boolean;
  model: string;
}> {
  const payload = await apiFetch("/api/v1/copilot/availability");

  return availabilitySchema.parse(envelopeData(payload)).copilot;
}

export async function sendCopilotMessage(
  message: string,
  history: CopilotTurn[],
): Promise<{ reply: string; model: string; disclaimer: string }> {
  const payload = await apiFetch("/api/v1/copilot/messages", {
    method: "POST",
    body: {
      message,
      timezone: browserTimezone(),
      ...(history.length > 0 ? { history: history.slice(-6) } : {}),
    },
  });

  return replySchema.parse(envelopeData(payload)).copilot;
}
