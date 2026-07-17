import type { Page } from "@playwright/test";

export const API = "http://localhost:8000";

export function user(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JZXUSER",
    name: "Sam Student",
    email: "sam@example.com",
    email_verified: true,
    primary_role: "student",
    ...overrides,
  };
}

export function onboarding(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    version: 1,
    status: "not_started",
    current_step: "institution",
    can_complete: false,
    completed_at: null,
    steps: {
      institution: "pending",
      program: "pending",
      study_stage: "pending",
      courses: "pending",
      goals: "pending",
      first_source: "pending",
    },
    profile: {
      institution_name: null,
      institution_country_code: null,
      department: null,
      degree: null,
      major: null,
      year_label: null,
      term_label: null,
    },
    course_drafts: [],
    goals: [],
    problems: [],
    first_source_draft: null,
    starter_context: null,
    ...overrides,
  };
}

export function envelope(data: unknown) {
  return { data, meta: { request_id: "e2e-request" } };
}

export async function mockCsrf(page: Page) {
  await page.route(`${API}/sanctum/csrf-cookie`, async (route) => {
    await route.fulfill({
      status: 204,
      headers: {
        "Set-Cookie": "XSRF-TOKEN=e2e-token; Path=/",
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
      },
    });
  });
}

export async function mockJson(
  page: Page,
  url: string,
  body: unknown,
  status = 200,
) {
  await page.route(url, async (route) => {
    await route.fulfill({
      status,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": "http://localhost:3100",
        "Access-Control-Allow-Credentials": "true",
        "Access-Control-Allow-Headers": "content-type,x-xsrf-token,accept",
        "Access-Control-Allow-Methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
      },
      body: JSON.stringify(body),
    });
  });
}

export function collection(data: unknown[]) {
  return {
    data,
    meta: {
      pagination: { next_cursor: null, previous_cursor: null, per_page: 20 },
      request_id: "e2e-request",
    },
    links: { next: null, previous: null },
  };
}

export function course(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jcourse00000000000000000",
    version: 1,
    title: "Data Structures",
    code: "CS201",
    description: null,
    term: null,
    status: "active",
    archived_at: null,
    created_at: "2026-07-10T09:00:00Z",
    updated_at: "2026-07-10T09:00:00Z",
    ...overrides,
  };
}

export function task(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jtask000000000000000000t",
    version: 1,
    title: "Problem set 2",
    description: null,
    course: {
      id: "01jcourse00000000000000000",
      version: 1,
      title: "Data Structures",
      code: "CS201",
      archive_status: "active",
    },
    due_at: "2026-07-17T09:30:00Z",
    status: "pending",
    completed_at: null,
    archive_status: "active",
    archived_at: null,
    created_at: "2026-07-10T09:00:00Z",
    updated_at: "2026-07-15T09:00:00Z",
    ...overrides,
  };
}

export function focusSession(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jsess000000000000000000s",
    version: 1,
    task: null,
    course: {
      id: "01jcourse00000000000000000",
      version: 1,
      title: "Data Structures",
      code: "CS201",
      archive_status: "active",
    },
    starts_at: "2026-07-17T04:00:00Z",
    ends_at: "2026-07-17T04:50:00Z",
    note: null,
    created_at: "2026-07-16T09:00:00Z",
    updated_at: "2026-07-16T09:00:00Z",
    ...overrides,
  };
}

export function plannerWindow(
  key: "date" | "week_start",
  value: string,
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    data: {
      timezone: "UTC",
      [key]: value,
      window: {
        starts_at: `${value}T00:00:00Z`,
        ends_at: `${value}T23:59:59Z`,
      },
      tasks: [task()],
      focus_sessions: [focusSession()],
      ...overrides,
    },
    meta: {
      summary: { tasks: 1, focus_sessions: 1 },
      has_more: { tasks: false, focus_sessions: false },
      limit: 50,
      request_id: "e2e-request",
    },
  };
}

export function resource(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jres0000000000000000000r",
    kind: "file",
    title: "Operating Systems Notes",
    description: null,
    topic: "Operating Systems",
    url: null,
    course: null,
    version: 2,
    file: {
      public_id: "01jfile000000000000000000f",
      original_name: "os-notes.pdf",
      declared_mime_type: "application/pdf",
      verified_mime_type: "application/pdf",
      expected_size: 204800,
      verified_size: 204800,
      status: "ready",
      ready_at: "2026-07-16T09:00:00Z",
    },
    created_at: "2026-07-16T08:00:00Z",
    updated_at: "2026-07-16T09:00:00Z",
    ...overrides,
  };
}

export function tool(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jtool000000000000000000t",
    name: "Concept Mapper",
    category: { key: "study-planning", name: "Study planning" },
    purpose: "Turn a dense reading into a labelled concept map.",
    selection_reason: "Chosen because it keeps sources visible and cited.",
    use_cases: ["Exam revision", "Literature review"],
    usage_guidance: "Paste your notes and let it group by theme.",
    limitations: "It cannot judge source quality for you.",
    cost_note: "Free tier is enough for coursework.",
    privacy_note: "Do not paste personally identifying data.",
    url: "https://tools.example.edu/concept-mapper",
    provenance: "Reviewed against the provider documentation.",
    last_reviewed_at: "2026-07-01T09:00:00Z",
    viewer_state: { saved: false, dismissed: false },
    ...overrides,
  };
}

export function prompt(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jprompt0000000000000000p",
    title: "Explain like a study partner",
    category: { key: "study-planning", name: "Study planning" },
    purpose: "Get a plain-language explanation you can verify.",
    template_body:
      "Explain {{concept}} at a first-year level with one example.",
    placeholders: ["concept"],
    expected_output: "A short explanation plus one worked example.",
    integrity_note: "Use it to understand, then write the answer yourself.",
    provenance: "Authored by the EduConnect learning team.",
    related_tools: [],
    last_reviewed_at: "2026-07-01T09:00:00Z",
    viewer_state: { saved: false, dismissed: false, copy_count: 0 },
    ...overrides,
  };
}

export function workflow(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jworkflow00000000000000w",
    title: "From reading to revision notes",
    category: { key: "study-planning", name: "Study planning" },
    goal: "Convert a chapter into revision notes with integrity.",
    expected_outcome: "A set of notes in your own words with citations.",
    integrity_note: "Every step keeps the original source attributed.",
    provenance: "Curated by the EduConnect learning team.",
    steps: [
      {
        number: 1,
        title: "Read and highlight",
        instruction: "Skim, then mark the load-bearing claims.",
        destination_action: "save_resource",
        tool: null,
        prompt: null,
        template: null,
      },
    ],
    last_reviewed_at: "2026-07-01T09:00:00Z",
    viewer_state: { saved: false, dismissed: false },
    ...overrides,
  };
}

export function guidanceBundle(
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    category: {
      key: "study-planning",
      name: "Study planning",
      description: "Plan and revise effectively.",
    },
    tools: [tool()],
    prompts: [prompt()],
    workflows: [workflow()],
    templates: [],
    ...overrides,
  };
}

export function template(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jtmpl0000000000000000000",
    title: "Assignment structure",
    category: { key: "academic-writing", name: "Academic writing" },
    summary: "A structured outline for high-quality assignments.",
    badge: "approved_free",
    integrity_note:
      "A scaffold to organize your own work, not to submit as-is.",
    provenance: "Curated by the EduConnect learning team.",
    latest_version: {
      number: 2,
      format: "markdown",
      body: "# Title\n\n## Introduction\n\n## Body\n\n## Conclusion",
    },
    last_reviewed_at: "2026-07-01T09:00:00Z",
    viewer_state: { saved: false, dismissed: false, active_copy_count: 0 },
    ...overrides,
  };
}

export function templateCopy(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jcopy0000000000000000000",
    destination: "dashboard",
    course: null,
    source: {
      template_id: "01jtmpl0000000000000000000",
      template_title: "Assignment structure",
      version_number: 2,
    },
    title: "Assignment structure",
    format: "markdown",
    body: "# Title\n\n## Introduction",
    version: 1,
    archived_at: null,
    created_at: "2026-07-16T09:00:00Z",
    updated_at: "2026-07-16T09:00:00Z",
    ...overrides,
  };
}

export function intakeItem(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01jintake00000000000000i",
    source: {
      type: "link",
      url: "https://example.edu/syllabus",
      resource: null,
    },
    context: "Course syllabus",
    state: "awaiting_review",
    failure_code: null,
    attempts: 1,
    extraction: { characters: 4200, content_type: "text/html" },
    classification: {
      provider: "rule_based",
      model: "v1",
      schema_version: "v1",
      latency_ms: 12,
    },
    events: [
      {
        event: "queued",
        from_state: "uploaded_or_linked",
        to_state: "queued",
        detail: "Queued for processing",
        occurred_at: "2026-07-18T09:00:00Z",
      },
    ],
    queued_at: "2026-07-18T09:00:00Z",
    started_at: "2026-07-18T09:00:05Z",
    finished_at: null,
    cancelled_at: null,
    created_at: "2026-07-18T09:00:00Z",
    updated_at: "2026-07-18T09:01:00Z",
    ...overrides,
  };
}

export function intakeSuggestion(
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    id: "01jsuggest0000000000000s",
    kind: "task",
    proposal: {
      title: "Read chapter 3",
      description: "Prepare for the quiz",
      due_at: "2026-07-25",
      course_id: null,
      url: null,
    },
    confidence: 0.82,
    reason: "The syllabus lists a chapter-3 quiz next week.",
    schema_version: "v1",
    status: "proposed",
    created_task_id: null,
    created_resource_id: null,
    created_at: null,
    ...overrides,
  };
}

export function knowledgeItem(
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    id: "01jknow000000000000000000k",
    version: 2,
    title: "Attention is all you need",
    summary: "Introduces the transformer architecture.",
    source: {
      type: "link",
      url: "https://arxiv.org/abs/1706.03762",
      resource: null,
    },
    citation: {
      authors: "Vaswani et al.",
      published_year: 2017,
      venue: "NeurIPS",
      doi: null,
    },
    tags: ["transformers"],
    collections: [],
    created_at: "2026-07-16T09:00:00Z",
    updated_at: "2026-07-16T09:00:00Z",
    ...overrides,
  };
}

export function knowledgeItemDetail(
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    ...knowledgeItem(),
    notes: [],
    links: [],
    research_topics: [],
    ...overrides,
  };
}

export function brainCollection(
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    id: "01jcoll000000000000000000c",
    version: 1,
    name: "AI",
    description: null,
    kind: "research",
    item_count: 3,
    created_at: "2026-07-16T09:00:00Z",
    updated_at: "2026-07-16T09:00:00Z",
    ...overrides,
  };
}

export function brainCollectionEnvelope(data: unknown[]) {
  return {
    data,
    meta: {
      summary: { total: data.length },
      pagination: { next_cursor: null, previous_cursor: null, per_page: 20 },
      request_id: "e2e-request",
    },
    links: { next: null, previous: null },
  };
}

export function researchTopic(
  overrides: Partial<Record<string, unknown>> = {},
) {
  return {
    id: "01jtopic00000000000000000t",
    version: 1,
    title: "Transformer interpretability",
    description: "How attention maps relate to reasoning.",
    keywords: ["attention", "probing"],
    source_count: 1,
    sources: [],
    created_at: "2026-07-16T09:00:00Z",
    updated_at: "2026-07-16T09:00:00Z",
    ...overrides,
  };
}

export function dashboard(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    timeframe: {
      timezone: "UTC",
      today: "2026-07-17",
      week_starts_on: "2026-07-13",
      week_ends_on: "2026-07-19",
    },
    cover: {
      name: "Sam Student",
      institution: "University of Dhaka",
      degree: "B.Sc.",
      major: "CSE",
      study_stage: "Year 2",
      term: { id: "01JTERM0000000000000000000", label: "Fall 2026" },
      active_course_count: 2,
    },
    quick_intake: { active_item: null, awaiting_review_count: 0 },
    whats_next: {
      tasks: [
        {
          id: "01jtask000000000000000000t",
          title: "Problem set 2",
          status: "pending",
          due_at: "2026-07-18T18:00:00Z",
          overdue: false,
          course: {
            id: "01jcourse00000000000000000",
            title: "Data Structures",
          },
        },
      ],
      overdue_count: 0,
      upcoming_count: 1,
    },
    tools: [],
    today: { due_task_count: 0, due_tasks: [], next_focus_session: null },
    second_brain: { total_item_count: 0, recent_items: [] },
    progress: {
      timeframe: {
        timezone: "UTC",
        starts_on: "2026-07-13",
        ends_on: "2026-07-19",
      },
      completed_task_count: 0,
      due_task_count: 1,
      focus_minutes: 0,
      daily_completed: [
        { date: "2026-07-13", completed: 0 },
        { date: "2026-07-14", completed: 0 },
      ],
      summary: "You completed 0 of 1 tasks due this week.",
      next_action: {
        kind: "task",
        id: "01jtask000000000000000000t",
        title: "Problem set 2",
        due_at: "2026-07-18T18:00:00Z",
      },
    },
    personal_rhythm: { has_activity: false, days: [] },
    ...overrides,
  };
}
