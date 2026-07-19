/**
 * Fixed sample dataset for the Live Demo - a simulated product experience
 * modeled on the approved dashboard hierarchy (doc 04) and the
 * INSPIRATIONAL dashboard reference, translated through the specs: no sync
 * buttons, no invented streaks or vs-last-week math, one progress
 * visualization, and every number derived from demo state.
 *
 * Tool entries are generic categories, not endorsements of real products -
 * the reviewed production catalog is curated separately by humans.
 *
 * Deterministic and isolated: no randomness, no clock dependence, no
 * network, no storage.
 */

export const DEMO_PERSONA = {
  name: "Sam",
  program: "B.Sc. Computer Science",
  term: "Fall 2026",
} as const;

export type DemoCourse = {
  id: string;
  code: string;
  name: string;
};

export const DEMO_COURSES: DemoCourse[] = [
  { id: "cs201", code: "CS-201", name: "Data Structures" },
  { id: "rm110", code: "RM-110", name: "Research Methods" },
];

export type DemoTaskSeed = {
  id: string;
  title: string;
  courseCode: string;
  due: string;
  /** Fixed human note like "3 days left" (deterministic, no clock). */
  dueNote?: string;
  /** 0 = Monday … 6 = Sunday; used for the weekly completion chart. */
  dayIndex: number;
  completed: boolean;
  origin: "starter" | "intake" | "template" | "manual";
};

export const INITIAL_TASKS: DemoTaskSeed[] = [
  {
    id: "seed-setup",
    title: "Set up course workspaces",
    courseCode: "CS-201",
    due: "Mon, Sep 21",
    dayIndex: 0,
    completed: true,
    origin: "starter",
  },
  {
    id: "seed-babbie",
    title: "Skim Babbie ch. 4 before seminar",
    courseCode: "RM-110",
    due: "Thu, Sep 24",
    dueNote: "3 days left",
    dayIndex: 3,
    completed: false,
    origin: "starter",
  },
  {
    id: "seed-pset",
    title: "Problem set 2: linked lists",
    courseCode: "CS-201",
    due: "Fri, Sep 25",
    dueNote: "4 days left",
    dayIndex: 4,
    completed: false,
    origin: "starter",
  },
];

export const JOURNEY_CARD = {
  title: "Your Journey",
  quote: "Discipline today creates freedom tomorrow.",
} as const;

export const NEXT_CLASS = {
  course: "CS-201 Data Structures",
  time: "Tomorrow, 10:00 to 11:30 AM",
  room: "Room CS-204, Engineering Block",
} as const;

export const FOCUS_SESSION = {
  label: "Deep Work",
  minutes: 60,
  topic: "Algorithms review",
} as const;

export type DemoTool = {
  id: string;
  name: string;
  blurb: string;
  integrityNote?: string;
};

/** Generic categories with reasons - the guidance format, not vendor picks. */
export const RECOMMENDED_TOOLS: DemoTool[] = [
  {
    id: "writing",
    name: "AI writing assistant",
    blurb: "Draft and refine academic text you started, always labeled.",
    integrityNote: "AI-assisted output stays labeled and editable.",
  },
  {
    id: "citation",
    name: "Citation helper",
    blurb: "Consistent citations in any style from your saved sources.",
  },
  {
    id: "workflow",
    name: "Study workflow planner",
    blurb: "Turn a goal into ordered steps with time estimates.",
  },
];

export type DemoNoteSeed = {
  id: string;
  title: string;
  courseCode: string;
  meta: string;
  date: string;
};

export const SECOND_BRAIN_SEEDS: DemoNoteSeed[] = [
  {
    id: "brain-notes",
    title: "Data structures: lecture notes",
    courseCode: "CS-201",
    meta: "Heaps, hashing, graph traversal",
    date: "Sep 18",
  },
  {
    id: "brain-bigo",
    title: "Time complexity summary",
    courseCode: "CS-201",
    meta: "Big-O, Omega, Theta with examples",
    date: "Sep 16",
  },
];

export const HOBBY_RHYTHM: Array<{ day: string; hobby?: string }> = [
  { day: "Mon", hobby: "Gym" },
  { day: "Tue" },
  { day: "Wed", hobby: "Reading" },
  { day: "Thu", hobby: "Gym" },
  { day: "Fri" },
  { day: "Sat", hobby: "Music" },
  { day: "Sun" },
];

export type DemoGoal = {
  id: string;
  label: string;
  description: string;
  tools: Array<{ name: string; why: string; integrityNote?: string }>;
  prompt: string;
  workflow: string[];
  templateId: string;
};

export const DEMO_GOALS: DemoGoal[] = [
  {
    id: "lit-review",
    label: "Write a literature review",
    description: "Turn a pile of papers into a structured, defensible review.",
    tools: [
      {
        name: "A reference manager",
        why: "Collects sources in one place, deduplicates them, and keeps citation data attached to every PDF.",
      },
      {
        name: "An AI reading assistant",
        why: "Good at first-pass summaries of papers you supply, which helps you decide what deserves a deep read.",
        integrityNote:
          "Verify every summary against the original before citing, and label AI-assisted notes as such.",
      },
    ],
    prompt:
      "Summarize the key argument, method, and limitations of the attached paper in under 200 words, then list three questions it leaves open.",
    workflow: [
      "Gather 8 to 10 candidate sources into your reading list.",
      "Skim abstracts and tag each source by theme.",
      "Deep-read the strongest five using the summary prompt.",
      "Fill the review matrix template and draft from it.",
    ],
    templateId: "lit-matrix",
  },
  {
    id: "exam-week",
    label: "Plan my exam week",
    description: "Five days, several topics, one realistic schedule.",
    tools: [
      {
        name: "A spaced-repetition flashcard system",
        why: "Retrieval practice beats rereading; spacing beats cramming.",
      },
      {
        name: "A focus timer",
        why: "Bounded sessions make an intimidating topic startable, and give your planner honest minutes to count.",
      },
    ],
    prompt:
      "Turn these lecture topics into a five-day study plan with three focus blocks per day, hardest topics first: [paste your topics]",
    workflow: [
      "List every examinable topic.",
      "Rank each topic by confidence, lowest first.",
      "Schedule three focus blocks per day against the ranking.",
      "End each day by turning weak points into flashcards.",
    ],
    templateId: "exam-planner",
  },
  {
    id: "lecture-summary",
    label: "Summarize a lecture",
    description: "From messy notes to an outline you'll actually revisit.",
    tools: [
      {
        name: "A structured note-taking method",
        why: "Cue columns and summaries separate what was said from what matters.",
      },
      {
        name: "An AI outliner",
        why: "Restructures your raw notes fast when the lecture outran your typing.",
        integrityNote:
          "Check the outline against the slides before trusting it for revision.",
      },
    ],
    prompt:
      "Convert these raw lecture notes into a structured outline with key terms highlighted, then add five self-test questions: [paste your notes]",
    workflow: [
      "Capture raw notes during or right after the lecture.",
      "Run the outline prompt and correct it against the slides.",
      "Attach the outline to the course so it's findable at exam time.",
      "Turn the five self-test questions into flashcards.",
    ],
    templateId: "cornell",
  },
  {
    id: "research-start",
    label: "Start a research topic",
    description: "Narrow a vague interest into a workable question.",
    tools: [
      {
        name: "An academic search engine",
        why: "Citation trails surface the conversation your topic belongs to.",
      },
      {
        name: "A reference manager",
        why: "Starting the reading list on day one is the cheapest favor you can do for month three.",
      },
    ],
    prompt:
      "Given the topic '[your topic]', list five candidate research questions ordered from broadest to narrowest, with one search query for each.",
    workflow: [
      "Draft one sentence describing what you want to know.",
      "Generate candidate questions and pick the narrowest viable one.",
      "Collect the first ten sources into a reading list with statuses.",
      "Review the list weekly and record what each source changed.",
    ],
    templateId: "research-kickoff",
  },
];

export type DemoTemplateCategory = "Research" | "Planning" | "Notes";

export type DemoTemplate = {
  id: string;
  name: string;
  description: string;
  category: DemoTemplateCategory;
  outline: string[];
  taskTitle: string;
};

/** Fixed due strings used when demo tasks are created by day index. */
export const DUE_BY_DAY = [
  "Mon, Sep 21",
  "Tue, Sep 22",
  "Wed, Sep 23",
  "Thu, Sep 24",
  "Fri, Sep 25",
  "Sat, Sep 26",
  "Sun, Sep 27",
] as const;

export const DAY_LABELS = [
  "Mon",
  "Tue",
  "Wed",
  "Thu",
  "Fri",
  "Sat",
  "Sun",
] as const;

export const DEMO_TEMPLATES: DemoTemplate[] = [
  {
    id: "lit-matrix",
    name: "Literature review matrix",
    description:
      "One row per source keeps claims, methods, and gaps comparable at a glance.",
    category: "Research",
    outline: [
      "Source and year",
      "Core claim",
      "Method",
      "Evidence quality",
      "Limitations",
      "Relevance to your question",
    ],
    taskTitle: "Fill in the literature review matrix",
  },
  {
    id: "exam-planner",
    name: "Exam week planner",
    description: "A day-by-day grid that survives contact with reality.",
    category: "Planning",
    outline: [
      "Day and topic",
      "Focus blocks planned",
      "Confidence before / after",
      "Carry-over for tomorrow",
    ],
    taskTitle: "Draft the exam week plan",
  },
  {
    id: "cornell",
    name: "Cornell notes",
    description: "The classic cue / notes / summary layout.",
    category: "Notes",
    outline: ["Cues and questions", "Notes", "Summary in your own words"],
    taskTitle: "Rework this week's notes in Cornell format",
  },
  {
    id: "research-kickoff",
    name: "Research kickoff",
    description: "One page that keeps the question and the reading honest.",
    category: "Research",
    outline: [
      "Research question",
      "Keywords and queries",
      "Reading list with status",
      "What changed this week",
    ],
    taskTitle: "Complete the research kickoff page",
  },
];

export type DemoSuggestion = {
  id: string;
  kind: "task" | "resource" | "note";
  label: string;
  detail: string;
  reason: string;
  /** Present on task suggestions; drives planner placement. */
  task?: {
    title: string;
    courseCode: string;
    due: string;
    dueNote?: string;
    dayIndex: number;
  };
};

export type DemoDocument = {
  id: string;
  name: string;
  kind: string;
  excerpt: string[];
  suggestions: DemoSuggestion[];
};

export const DEMO_DOCUMENTS: DemoDocument[] = [
  {
    id: "syllabus",
    name: "CS-201 syllabus (sample).pdf",
    kind: "Course syllabus",
    excerpt: [
      "CS-201: Data Structures, Fall term (sample)",
      "Week 3: Linked lists, stacks, queues",
      "Assignment 1 due October 2 (10%)",
      "Midterm examination on October 21, weeks 1-6 inclusive",
    ],
    suggestions: [
      {
        id: "syllabus-task-1",
        kind: "task",
        label: "Assignment 1",
        detail: "Due Fri, Oct 2 · CS-201 Data Structures",
        reason: 'Found "due" next to a date with a weight marker (10%).',
        task: {
          title: "Assignment 1: data structures",
          courseCode: "CS-201",
          due: "Fri, Oct 2",
          dueNote: "11 days left",
          dayIndex: 4,
        },
      },
      {
        id: "syllabus-task-2",
        kind: "task",
        label: "Midterm exam",
        detail: "Wed, Oct 21 · scope weeks 1-6",
        reason: '"Examination" plus an explicit date on line 4.',
        task: {
          title: "Prepare for CS-201 midterm",
          courseCode: "CS-201",
          due: "Wed, Oct 21",
          dueNote: "30 days left",
          dayIndex: 2,
        },
      },
      {
        id: "syllabus-resource",
        kind: "resource",
        label: "Save the syllabus to CS-201",
        detail: "Type: Syllabus · tags: syllabus, data-structures",
        reason: "Document structure matches a course syllabus.",
      },
    ],
  },
  {
    id: "lecture-notes",
    name: "Research methods - week 2 notes (sample).docx",
    kind: "Lecture notes",
    excerpt: [
      "RM-110 Research Methods, week 2 (sample)",
      "Sampling: probability vs convenience; bias sources",
      "Reading response on Babbie ch. 4 due September 25",
      "Key terms: validity, reliability, operationalization",
    ],
    suggestions: [
      {
        id: "notes-task-1",
        kind: "task",
        label: "Reading response",
        detail: "Due Fri, Sep 25 · RM-110 Research Methods",
        reason: '"due" with a date beside a named reading.',
        task: {
          title: "Reading response: Babbie ch. 4",
          courseCode: "RM-110",
          due: "Fri, Sep 25",
          dueNote: "4 days left",
          dayIndex: 4,
        },
      },
      {
        id: "notes-resource",
        kind: "resource",
        label: "Save the notes to RM-110",
        detail: "Type: Lecture notes · tags: sampling, methods",
        reason: "Header names the course and week.",
      },
      {
        id: "notes-note",
        kind: "note",
        label: "Second Brain: key terms note",
        detail: "validity, reliability, operationalization → linked to source",
        reason: "A 'key terms' line usually earns a knowledge note.",
      },
    ],
  },
];

export const ANALYSIS_STAGES = [
  "Validating the sample document",
  "Extracting readable text",
  "Finding dates and course signals",
  "Drafting suggestions with reasons",
] as const;
