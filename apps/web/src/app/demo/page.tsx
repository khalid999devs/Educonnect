import type { Metadata } from "next";

import { DemoApp } from "@/components/demo/demo-app";

export const metadata: Metadata = {
  title: "Live Demo",
  description:
    "The EduConnect student workspace, simulated full-screen in your browser on sample data: dashboard, Smart Intake, planner, AI tools guidance, templates, Second Brain, and truthful progress. No signup.",
  openGraph: {
    title: "EduConnect Live Demo",
    description:
      "The student workspace simulated full-screen in your browser on sample data — no signup.",
    type: "website",
  },
};

export default function DemoPage() {
  return <DemoApp />;
}
