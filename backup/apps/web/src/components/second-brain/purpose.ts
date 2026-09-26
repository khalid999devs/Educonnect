import {
  BookOpen,
  FlaskConical,
  GraduationCap,
  Library,
  Lightbulb,
  ListChecks,
  ScrollText,
  Zap,
  type LucideIcon,
} from "lucide-react";

import type { SectionAccentKey } from "@/components/shell/section-accent";
import type { StudyArtifactKind } from "@/lib/api/study";
import type { KnowledgePurpose } from "@/lib/api/second-brain";

/**
 * The four purposes are the organizing principle of Second Brain, and the
 * reason the workspace can offer the right ready actions without asking again.
 *
 * The backend pre-selects one; it is ADVISORY and never authoritative. A wrong
 * pre-selection must be a one-click correction, which is why every surface
 * that shows a purpose also shows all four choices rather than an edit affordance.
 */
export type PurposeDescriptor = {
  value: KnowledgePurpose;
  label: string;
  /** Second person, describes what the student gets - not what the AI does. */
  description: string;
  icon: LucideIcon;
  accent: SectionAccentKey;
  /** Ready actions offered in the workspace, most useful first. */
  actions: readonly StudyArtifactKind[];
};

export const PURPOSES: readonly PurposeDescriptor[] = [
  {
    value: "resource",
    label: "Resource",
    description: "Keep it filed and findable. Read it when you need it.",
    icon: Library,
    accent: "resources",
    actions: ["summary"],
  },
  {
    value: "study",
    label: "Study and learn",
    description: "Work through it properly and come out understanding it.",
    icon: BookOpen,
    accent: "study",
    actions: ["summary", "topic_explanation", "quick_learn"],
  },
  {
    value: "research",
    label: "Research",
    description: "Read it critically alongside your other sources.",
    icon: FlaskConical,
    accent: "secondBrain",
    actions: ["summary", "topic_explanation"],
  },
  {
    value: "exam",
    label: "Exam preparation",
    description: "Get exam-ready: recall it, then test yourself on it.",
    icon: GraduationCap,
    accent: "progress",
    actions: ["quick_learn", "exam_questions", "summary"],
  },
];

const BY_VALUE = new Map<KnowledgePurpose, PurposeDescriptor>(
  PURPOSES.map((purpose) => [purpose.value, purpose]),
);

export function purposeDescriptor(
  purpose: KnowledgePurpose | null | undefined,
): PurposeDescriptor | null {
  return purpose ? (BY_VALUE.get(purpose) ?? null) : null;
}

/** Actions to offer when no purpose has been recorded. Null purpose is a real
 * value - every row captured before purposes existed reads back as null - so
 * it gets a useful default rather than an empty workspace. */
export const DEFAULT_ACTIONS: readonly StudyArtifactKind[] = ["summary"];

export function actionsForPurpose(
  purpose: KnowledgePurpose | null | undefined,
): readonly StudyArtifactKind[] {
  return purposeDescriptor(purpose)?.actions ?? DEFAULT_ACTIONS;
}

export type StudyActionDescriptor = {
  kind: StudyArtifactKind;
  label: string;
  description: string;
  icon: LucideIcon;
};

export const STUDY_ACTIONS: Record<StudyArtifactKind, StudyActionDescriptor> = {
  summary: {
    kind: "summary",
    label: "Summarize",
    description: "The document's own argument, condensed.",
    icon: ScrollText,
  },
  topic_explanation: {
    kind: "topic_explanation",
    label: "Explain each topic simply",
    description: "Every topic in the document, in plain language.",
    icon: Lightbulb,
  },
  quick_learn: {
    kind: "quick_learn",
    label: "Quick learn",
    description: "The shortest path to understanding this.",
    icon: Zap,
  },
  exam_questions: {
    kind: "exam_questions",
    label: "Quick exam preparation",
    description: "Practice questions drawn from this document.",
    icon: ListChecks,
  },
};

/** The list-view filter accepts the four purposes plus a sentinel for rows
 * with no purpose recorded. `all` is the client's own "no filter" value and is
 * never sent to the API. */
export type PurposeFilter = KnowledgePurpose | "none" | "all";

export function isPurposeFilter(value: string): value is PurposeFilter {
  return (
    value === "all" ||
    value === "none" ||
    BY_VALUE.has(value as KnowledgePurpose)
  );
}
