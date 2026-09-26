"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  cn,
  FormField,
  Input,
  Select,
  Textarea,
} from "@educonnect/ui";
import {
  BookMarked,
  CheckCircle2,
  CircleSlash,
  FileText,
  ListTodo,
} from "lucide-react";
import { useMemo, useState } from "react";

import type { Course } from "@/lib/api/courses";
import type { ConfirmDecision, IntakeSuggestion } from "@/lib/api/intake";

type SuggestionKind = IntakeSuggestion["kind"];

type Draft = {
  kind: SuggestionKind;
  action: "apply" | "dismiss" | undefined;
  title: string;
  description: string;
  due_at: string;
  course_id: string;
  url: string;
};

const kindLabel: Record<SuggestionKind, string> = {
  task: "Suggested task",
  resource: "Suggested resource",
  knowledge_item: "Suggested knowledge item",
};

function KindIcon({ kind }: { kind: SuggestionKind }) {
  if (kind === "task") {
    return (
      <ListTodo aria-hidden="true" className="size-5 text-brand-primary" />
    );
  }
  if (kind === "knowledge_item") {
    return (
      <BookMarked aria-hidden="true" className="size-5 text-status-success" />
    );
  }

  return <FileText aria-hidden="true" className="size-5 text-status-info" />;
}

function confidenceLabel(confidence: number): {
  label: string;
  variant: "success" | "warning" | "neutral";
} {
  if (confidence >= 0.75) {
    return { label: "High confidence", variant: "success" };
  }
  if (confidence >= 0.4) {
    return { label: "Medium confidence", variant: "warning" };
  }
  return { label: "Low confidence", variant: "neutral" };
}

/**
 * Editable review of AI suggestions. Every field is a controlled input the
 * student can correct before anything is created; the reason and all proposal
 * text render as plain, escaped React text (never interpreted as markup), so a
 * prompt-injection payload in the source cannot execute or restyle the page.
 */
export function IntakeReview({
  suggestions,
  courses,
  busy,
  error,
  onConfirm,
}: {
  suggestions: IntakeSuggestion[];
  courses: Course[];
  busy: boolean;
  error: string | null;
  onConfirm: (decisions: ConfirmDecision[]) => void;
}) {
  const initial = useMemo(() => {
    const map: Record<string, Draft> = {};

    for (const suggestion of suggestions) {
      map[suggestion.id] = {
        kind: suggestion.kind,
        action: undefined,
        title: suggestion.proposal.title ?? "",
        description: suggestion.proposal.description ?? "",
        due_at: suggestion.proposal.due_at ?? "",
        course_id: suggestion.proposal.course_id ?? "",
        url: suggestion.proposal.url ?? "",
      };
    }

    return map;
  }, [suggestions]);

  const [drafts, setDrafts] = useState<Record<string, Draft>>(initial);

  const setDraft = (id: string, patch: Partial<Draft>) =>
    setDrafts((current) => ({
      ...current,
      [id]: { ...current[id]!, ...patch },
    }));

  const decidedCount = Object.values(drafts).filter(
    (draft) => draft.action !== undefined,
  ).length;
  const applyCount = Object.values(drafts).filter(
    (draft) => draft.action === "apply",
  ).length;

  const confirm = () => {
    const decisions: ConfirmDecision[] = [];

    for (const suggestion of suggestions) {
      const draft = drafts[suggestion.id]!;

      if (draft.action === "dismiss") {
        decisions.push({ id: suggestion.id, action: "dismiss" });
      } else if (draft.action === "apply") {
        decisions.push({
          id: suggestion.id,
          action: "apply",
          overrides: {
            title: draft.title.trim() === "" ? null : draft.title.trim(),
            description:
              draft.description.trim() === "" ? null : draft.description.trim(),
            due_at: draft.due_at === "" ? null : draft.due_at,
            course_id: draft.course_id === "" ? null : draft.course_id,
            url: draft.url.trim() === "" ? null : draft.url.trim(),
          },
        });
      }
    }

    onConfirm(decisions);
  };

  return (
    <div className="space-y-3">
      <div className="flex items-center justify-between gap-3">
        <div>
          <h3 className="text-h4 text-text-primary">Review suggestions</h3>
          <p className="text-caption text-text-muted">
            Nothing is created until you confirm. Edit anything before applying.
          </p>
        </div>
        <Badge variant="neutral">
          {applyCount} to create · {suggestions.length - decidedCount} undecided
        </Badge>
      </div>

      {error ? (
        <Alert variant="error" title="The confirmation could not be completed">
          {error}
        </Alert>
      ) : null}

      {suggestions.map((suggestion) => {
        const draft = drafts[suggestion.id]!;
        const confidence = confidenceLabel(suggestion.confidence);
        const decided = suggestion.status !== "proposed";

        return (
          <Card
            key={suggestion.id}
            className={cn(
              draft.action === "dismiss" ? "opacity-60" : undefined,
              draft.action === "apply"
                ? "ring-1 ring-brand-primary/30"
                : undefined,
            )}
          >
            <CardHeader className="space-y-2">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <CardTitle className="flex items-center gap-2 text-h4">
                  <KindIcon kind={draft.kind} />
                  {kindLabel[draft.kind]}
                </CardTitle>
                <div className="flex items-center gap-2">
                  <Badge variant={confidence.variant}>
                    {confidence.label} ·{" "}
                    {Math.round(suggestion.confidence * 100)}%
                  </Badge>
                  {decided ? (
                    <Badge variant="neutral">Already {suggestion.status}</Badge>
                  ) : null}
                </div>
              </div>
              {/* reason is untrusted source-derived text: rendered escaped. */}
              <p className="text-body text-text-secondary">
                <span className="font-medium text-text-primary">Why: </span>
                {suggestion.reason}
              </p>
            </CardHeader>
            <CardContent className="space-y-3">
              <FormField label="Title">
                {(control) => (
                  <Input
                    {...control}
                    value={draft.title}
                    maxLength={160}
                    onChange={(event) =>
                      setDraft(suggestion.id, { title: event.target.value })
                    }
                    disabled={decided}
                  />
                )}
              </FormField>

              {draft.kind === "resource" ? null : (
                <FormField
                  label={draft.kind === "task" ? "Description" : "Summary"}
                >
                  {(control) => (
                    <Textarea
                      {...control}
                      value={draft.description}
                      maxLength={2000}
                      onChange={(event) =>
                        setDraft(suggestion.id, {
                          description: event.target.value,
                        })
                      }
                      disabled={decided}
                    />
                  )}
                </FormField>
              )}

              {draft.kind === "task" ? (
                <>
                  <div className="grid gap-3 sm:grid-cols-2">
                    <FormField label="Due date">
                      {(control) => (
                        <Input
                          {...control}
                          type="date"
                          value={draft.due_at}
                          onChange={(event) =>
                            setDraft(suggestion.id, {
                              due_at: event.target.value,
                            })
                          }
                          disabled={decided}
                        />
                      )}
                    </FormField>
                    <FormField label="Course">
                      {(control) => (
                        <Select
                          {...control}
                          value={draft.course_id}
                          onChange={(event) =>
                            setDraft(suggestion.id, {
                              course_id: event.target.value,
                            })
                          }
                          disabled={decided}
                        >
                          <option value="">No course</option>
                          {courses.map((course) => (
                            <option key={course.id} value={course.id}>
                              {course.code ? `${course.code} · ` : ""}
                              {course.title}
                            </option>
                          ))}
                        </Select>
                      )}
                    </FormField>
                  </div>
                </>
              ) : (
                <FormField
                  label="Link"
                  hint={
                    draft.kind === "knowledge_item"
                      ? "HTTPS only, optional"
                      : "HTTPS only"
                  }
                >
                  {(control) => (
                    <Input
                      {...control}
                      type="url"
                      value={draft.url}
                      maxLength={2048}
                      onChange={(event) =>
                        setDraft(suggestion.id, { url: event.target.value })
                      }
                      disabled={decided}
                    />
                  )}
                </FormField>
              )}

              {!decided ? (
                <div className="flex flex-wrap items-center gap-2 border-t border-border-subtle pt-3">
                  <Button
                    variant={draft.action === "apply" ? "primary" : "secondary"}
                    size="sm"
                    aria-pressed={draft.action === "apply"}
                    onClick={() =>
                      setDraft(suggestion.id, {
                        action: draft.action === "apply" ? undefined : "apply",
                      })
                    }
                  >
                    <CheckCircle2 aria-hidden="true" className="size-4" />
                    {draft.action === "apply" ? "Will create" : "Create this"}
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    aria-pressed={draft.action === "dismiss"}
                    className={
                      draft.action === "dismiss" ? "text-text-muted" : undefined
                    }
                    onClick={() =>
                      setDraft(suggestion.id, {
                        action:
                          draft.action === "dismiss" ? undefined : "dismiss",
                      })
                    }
                  >
                    <CircleSlash aria-hidden="true" className="size-4" />
                    Dismiss
                  </Button>
                </div>
              ) : null}
            </CardContent>
          </Card>
        );
      })}

      <div className="flex flex-wrap items-center justify-between gap-2">
        <p className="text-caption text-text-muted">
          Undecided suggestions are dismissed when you confirm.
        </p>
        <Button
          isLoading={busy}
          loadingLabel="Creating"
          disabled={decidedCount === 0}
          onClick={confirm}
        >
          Confirm{applyCount > 0 ? ` and create ${applyCount}` : ""}
        </Button>
      </div>
    </div>
  );
}
