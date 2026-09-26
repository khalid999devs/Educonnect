import {
  BookOpenCheck,
  GraduationCap,
  ListChecks,
  Rocket,
  ScrollText,
  Sparkles,
  type LucideIcon,
} from "lucide-react";

import type { StudyArtifactKind } from "@/lib/api/study";

/**
 * The three things a student comes to this section to do. The intent is a
 * client-side framing device only: it decides which ready-made actions are
 * offered first and how the walkthrough is worded. It is never sent to the
 * API, so it can never silently change what the backend generates.
 */
export type StudyIntent = "study" | "learn" | "exam";

export type StudyIntentConfig = {
  value: StudyIntent;
  label: string;
  tagline: string;
  description: string;
  icon: LucideIcon;
  /** Ready-made actions, most useful first. */
  kinds: readonly StudyArtifactKind[];
  /** Heading for the material step, which differs per intent. */
  materialTitle: string;
};

/** Keyed so every intent resolves without an index-out-of-range fallback. */
export const STUDY_INTENT_CONFIG: Record<StudyIntent, StudyIntentConfig> = {
  study: {
    value: "study",
    label: "Study",
    tagline: "Work through material you already have",
    description:
      "Condense a document into a summary you can revise from, then go deeper on the parts that matter.",
    icon: BookOpenCheck,
    kinds: ["summary", "topic_explanation"],
    materialTitle: "What are you studying?",
  },
  learn: {
    value: "learn",
    label: "Learn",
    tagline: "Meet something new for the first time",
    description:
      "A quick-learn walkthrough that starts from the basics, plus explanations of the topics it introduces.",
    icon: Rocket,
    kinds: ["quick_learn", "topic_explanation"],
    materialTitle: "What are you learning?",
  },
  exam: {
    value: "exam",
    label: "Exam preparation",
    tagline: "Practise against your own material",
    description:
      "Practice questions drawn only from the document you choose, with the answer and the reasoning shown when you ask for them.",
    icon: GraduationCap,
    kinds: ["exam_questions", "summary"],
    materialTitle: "What are you preparing on?",
  },
};

/** Display order for the picker. */
export const STUDY_INTENTS: readonly StudyIntentConfig[] = [
  STUDY_INTENT_CONFIG.study,
  STUDY_INTENT_CONFIG.learn,
  STUDY_INTENT_CONFIG.exam,
];

export function findStudyIntent(value: StudyIntent): StudyIntentConfig {
  return STUDY_INTENT_CONFIG[value];
}

export type StudyKindConfig = {
  label: string;
  /** What the student gets, in their words, not the model's. */
  description: string;
  icon: LucideIcon;
  cta: string;
};

export const STUDY_KINDS: Record<StudyArtifactKind, StudyKindConfig> = {
  summary: {
    label: "Summary",
    description:
      "The document condensed into an overview, a handful of sections, and the points worth remembering.",
    icon: ScrollText,
    cta: "Summarise it",
  },
  topic_explanation: {
    label: "Topic explanation",
    description:
      "The ideas in the document explained one at a time, in the order they build on each other.",
    icon: Sparkles,
    cta: "Explain the topics",
  },
  quick_learn: {
    label: "Quick learn",
    description:
      "A short walkthrough that assumes no prior knowledge and gets you to a working understanding.",
    icon: Rocket,
    cta: "Walk me through it",
  },
  exam_questions: {
    label: "Exam questions",
    description:
      "Practice questions written only from this document, each with its answer and the reasoning behind it.",
    icon: ListChecks,
    cta: "Write practice questions",
  },
};

/** Every kind, in a stable order, for the history filter and the CTA row. */
export const STUDY_KIND_ORDER: readonly StudyArtifactKind[] = [
  "summary",
  "topic_explanation",
  "quick_learn",
  "exam_questions",
];

/** Ordered so an intent's own actions lead and the rest stay reachable. */
export function orderedKindsForIntent(
  intent: StudyIntent,
): readonly StudyArtifactKind[] {
  const preferred = findStudyIntent(intent).kinds;

  return [
    ...preferred,
    ...STUDY_KIND_ORDER.filter((kind) => !preferred.includes(kind)),
  ];
}
