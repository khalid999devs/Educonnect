import type { Metadata } from "next";

import { ToolsPromptsView } from "@/components/guidance/tools-prompts-view";

export const metadata: Metadata = {
  title: "Tools & Prompts",
};

export default function ToolsPromptsPage() {
  return <ToolsPromptsView />;
}
