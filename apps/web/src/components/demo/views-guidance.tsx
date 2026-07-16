"use client";

import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
  cn,
  FormField,
  Textarea,
} from "@educonnect/ui";
import {
  ArrowRight,
  CircleCheck,
  LayoutTemplate,
  ListChecks,
  NotebookPen,
  Search,
  Sparkles,
  type LucideIcon,
} from "lucide-react";
import { useState } from "react";

import { useDemo } from "./demo-app";
import {
  DEMO_COURSES,
  DEMO_GOALS,
  DEMO_TEMPLATES,
  type DemoGoal,
  type DemoTemplateCategory,
} from "./demo-data";
import { PageCover } from "./page-cover";

const TOOL_TILE_TINTS = [
  "bg-status-info/12 text-status-info",
  "bg-status-ai/12 text-status-ai",
];

export function ToolsView() {
  const { dispatch } = useDemo();
  const [goal, setGoal] = useState<DemoGoal>(DEMO_GOALS[0] as DemoGoal);
  const [promptDraft, setPromptDraft] = useState<string>(goal.prompt);

  const selectGoal = (nextGoal: DemoGoal) => {
    setGoal(nextGoal);
    setPromptDraft(nextGoal.prompt);
  };

  const goalTemplate = DEMO_TEMPLATES.find((t) => t.id === goal.templateId);

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/study-desk.jpg"
        eyebrow="AI Tools"
        title="The right tool, with the reasoning"
        subtitle="Start from a goal — every recommendation explains why it fits and how to use it responsibly."
      />

      <div
        role="group"
        aria-label="Pick a goal"
        className="flex flex-wrap gap-2"
      >
        {DEMO_GOALS.map((candidate) => {
          const selected = goal.id === candidate.id;

          return (
            <button
              key={candidate.id}
              type="button"
              aria-pressed={selected}
              onClick={() => selectGoal(candidate)}
              className={cn(
                "rounded-full border px-4 py-2 text-body font-medium transition-colors",
                "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                selected
                  ? "border-brand-primary/50 bg-bg-interactive text-brand-primary"
                  : "border-border-default bg-bg-surface text-text-secondary hover:text-text-primary",
              )}
            >
              {candidate.label}
            </button>
          );
        })}
      </div>

      <div className="grid gap-4 xl:grid-cols-[1fr_320px]">
        <div className="space-y-4">
          <Card>
            <CardHeader className="mb-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <CardTitle as="h3">Recommended for: {goal.label}</CardTitle>
                <Badge variant="neutral">Sample entries</Badge>
              </div>
            </CardHeader>
            <CardContent>
              <div className="grid gap-3 sm:grid-cols-2">
                {goal.tools.map((tool, index) => (
                  <div
                    key={tool.name}
                    className="rounded-lg border border-border-subtle p-4 transition-colors hover:border-border-strong"
                  >
                    <span
                      className={cn(
                        "flex size-11 items-center justify-center rounded-md",
                        TOOL_TILE_TINTS[index % TOOL_TILE_TINTS.length],
                      )}
                    >
                      <Sparkles aria-hidden="true" className="size-5" />
                    </span>
                    <p className="mt-3 text-body font-semibold text-text-primary">
                      {tool.name}
                    </p>
                    <p className="mt-1 text-body text-text-secondary">
                      <span className="font-medium text-text-primary">
                        Why this fits:{" "}
                      </span>
                      {tool.why}
                    </p>
                    {tool.integrityNote ? (
                      <p className="mt-2 text-caption text-status-ai">
                        Integrity note: {tool.integrityNote}
                      </p>
                    ) : null}
                  </div>
                ))}
              </div>
              <p className="mt-3 text-caption text-text-muted">
                Generic sample categories, not endorsements — the reviewed
                catalog is curated by humans with cost and privacy notes.
              </p>
            </CardContent>
          </Card>

          <FormField
            label="Editable prompt"
            hint="Edit it right here — in the product you'd copy it into your tool of choice."
          >
            {(control) => (
              <Textarea
                value={promptDraft}
                onChange={(event) => setPromptDraft(event.target.value)}
                rows={4}
                {...control}
              />
            )}
          </FormField>
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader className="mb-3">
              <CardTitle as="h3">How to run this goal</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2.5">
              {goal.workflow.map((step, index) => (
                <div
                  key={step}
                  className="flex items-start gap-3 rounded-md border border-border-subtle px-3.5 py-3"
                >
                  <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-bg-interactive text-body font-semibold tabular-nums text-brand-primary">
                    {index + 1}
                  </span>
                  <p className="text-body text-text-secondary">{step}</p>
                </div>
              ))}
            </CardContent>
          </Card>

          {goalTemplate ? (
            <Card className="bg-bg-interactive">
              <CardContent className="space-y-3">
                <p className="text-body text-text-primary">
                  This goal ends in a template:{" "}
                  <span className="font-semibold">{goalTemplate.name}</span>
                </p>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() =>
                    dispatch({ type: "navigate", view: "templates" })
                  }
                >
                  Open templates
                  <ArrowRight aria-hidden="true" className="size-4" />
                </Button>
              </CardContent>
            </Card>
          ) : null}
        </div>
      </div>
    </div>
  );
}

const CATEGORY_ICONS: Record<DemoTemplateCategory, LucideIcon> = {
  Research: Search,
  Planning: ListChecks,
  Notes: NotebookPen,
};

const CATEGORY_TINTS: Record<DemoTemplateCategory, string> = {
  Research: "bg-status-research/12 text-status-research",
  Planning: "bg-status-info/12 text-status-info",
  Notes: "bg-status-ai/12 text-status-ai",
};

export function TemplatesView() {
  const { state, dispatch } = useDemo();
  const [previewId, setPreviewId] = useState<string | null>(null);
  const [destinationFor, setDestinationFor] = useState<string | null>(null);
  const [category, setCategory] = useState<"All" | DemoTemplateCategory>("All");

  const categories: Array<"All" | DemoTemplateCategory> = [
    "All",
    ...Array.from(new Set(DEMO_TEMPLATES.map((t) => t.category))),
  ];
  const visibleTemplates = DEMO_TEMPLATES.filter(
    (template) => category === "All" || template.category === category,
  );
  const usedTemplates = DEMO_TEMPLATES.filter((t) =>
    state.usedTemplateIds.includes(t.id),
  );

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/library-curve.jpg"
        eyebrow="Templates"
        title="Use it, make it yours"
        subtitle="Academic templates you can preview, copy to a destination, and edit independently."
      />

      <div
        role="group"
        aria-label="Filter templates by category"
        className="flex flex-wrap gap-2"
      >
        {categories.map((candidate) => {
          const selected = category === candidate;

          return (
            <button
              key={candidate}
              type="button"
              aria-pressed={selected}
              onClick={() => setCategory(candidate)}
              className={cn(
                "rounded-full border px-4 py-1.5 text-body font-medium transition-colors",
                "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                selected
                  ? "border-brand-primary/50 bg-brand-primary text-white"
                  : "border-border-default bg-bg-surface text-text-secondary hover:text-text-primary",
              )}
            >
              {candidate}
            </button>
          );
        })}
      </div>

      <div className="grid gap-4 xl:grid-cols-[1fr_300px]">
        <div className="grid gap-4 sm:grid-cols-2">
          {visibleTemplates.map((template) => {
            const used = state.usedTemplateIds.includes(template.id);
            const previewing = previewId === template.id;
            const choosingDestination = destinationFor === template.id;
            const Icon = CATEGORY_ICONS[template.category];

            return (
              <Card key={template.id} className="flex flex-col">
                <CardHeader className="mb-2">
                  <div className="flex items-start justify-between gap-2">
                    <span
                      className={cn(
                        "flex size-11 items-center justify-center rounded-md",
                        CATEGORY_TINTS[template.category],
                      )}
                    >
                      <Icon aria-hidden="true" className="size-5" />
                    </span>
                    <Badge variant="success">Approved · free</Badge>
                  </div>
                  <CardTitle as="h3">{template.name}</CardTitle>
                  <CardDescription>{template.description}</CardDescription>
                </CardHeader>
                <CardContent className="mt-auto space-y-3">
                  {previewing ? (
                    <div className="rounded-md border border-border-subtle bg-bg-subtle p-4">
                      <ul className="list-disc space-y-1.5 pl-5">
                        {template.outline.map((line) => (
                          <li key={line}>{line}</li>
                        ))}
                      </ul>
                    </div>
                  ) : null}

                  {choosingDestination ? (
                    <div className="space-y-2">
                      <p className="text-caption text-text-muted">
                        Save your copy to:
                      </p>
                      <div className="flex flex-wrap gap-2">
                        {DEMO_COURSES.map((course) => (
                          <Button
                            key={course.id}
                            variant="secondary"
                            size="sm"
                            onClick={() => {
                              dispatch({
                                type: "useTemplate",
                                templateId: template.id,
                                courseCode: course.code,
                              });
                              setDestinationFor(null);
                            }}
                          >
                            {course.code} {course.name}
                          </Button>
                        ))}
                      </div>
                    </div>
                  ) : (
                    <div className="flex flex-wrap items-center gap-2">
                      <Button
                        variant="secondary"
                        size="sm"
                        aria-expanded={previewing}
                        onClick={() =>
                          setPreviewId(previewing ? null : template.id)
                        }
                      >
                        {previewing ? "Hide preview" : "Preview"}
                      </Button>
                      {used ? (
                        <p className="flex items-center gap-1.5 text-caption text-status-success">
                          <CircleCheck aria-hidden="true" className="size-4" />
                          Copy created — see the planner.
                        </p>
                      ) : (
                        <Button
                          size="sm"
                          onClick={() => setDestinationFor(template.id)}
                        >
                          Use template
                        </Button>
                      )}
                    </div>
                  )}
                </CardContent>
              </Card>
            );
          })}
        </div>

        <div className="space-y-4">
          <Card>
            <CardHeader className="mb-3">
              <CardTitle as="h3" className="flex items-center gap-2">
                <LayoutTemplate
                  aria-hidden="true"
                  className="size-4 text-brand-primary"
                />
                Your copies
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-2.5">
              {usedTemplates.length === 0 ? (
                <p className="text-body text-text-secondary">
                  No copies yet — use a template and it appears here as an
                  independent, editable copy.
                </p>
              ) : (
                <>
                  {usedTemplates.map((template) => (
                    <div
                      key={template.id}
                      className="flex items-center gap-2.5 rounded-md border border-border-subtle px-3 py-2.5"
                    >
                      <CircleCheck
                        aria-hidden="true"
                        className="size-4 shrink-0 text-status-success"
                      />
                      <span className="min-w-0 flex-1 truncate text-body text-text-primary">
                        {template.name}
                      </span>
                    </div>
                  ))}
                  <Button
                    variant="secondary"
                    size="sm"
                    fullWidth
                    onClick={() =>
                      dispatch({ type: "navigate", view: "planner" })
                    }
                  >
                    See the follow-up task
                    <ArrowRight aria-hidden="true" className="size-4" />
                  </Button>
                </>
              )}
            </CardContent>
          </Card>

          <Card className="bg-bg-interactive">
            <CardContent>
              <p className="text-body text-text-primary">
                Copies never change when the source template updates — your
                edits are yours.
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
