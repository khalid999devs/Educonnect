import type { Metadata } from "next";

import { ConsoleOverview } from "@/components/console/console-overview";

export const metadata: Metadata = {
  title: "Overview",
};

export default function ConsoleOverviewPage() {
  return <ConsoleOverview />;
}
