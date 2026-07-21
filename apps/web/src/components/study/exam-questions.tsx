"use client";

import { Badge, Button, cn } from "@educonnect/ui";
import { Check, Eye, EyeOff, Info } from "lucide-react";
import { useRef, useState } from "react";

import type { ExamQuestion } from "@/lib/api/study";

const LETTERS = ["A", "B", "C", "D", "E", "F"] as const;

/**
 * Exam questions get their own presentation because the failure mode is worse
 * here than anywhere else in the product: a confidently wrong answer is more
 * damaging than no answer at all.
 *
 * Three rules hold this together:
 *
 * 1. The answer is never visible until the student asks for it. Revealing is
 *    per-question and reversible, and happens inline - no dialog.
 * 2. The sourcing line is always shown alongside the answer, never as fine
 *    print somewhere else, so the student sees where the answer came from at
 *    the same moment they see the answer.
 * 3. Nothing is scored. A selected option is marked as the student's choice
 *    and compared against the stated answer, but no total, streak, or grade
 *    is computed anywhere.
 *
 * All question text is model-generated from an untrusted document and is
 * rendered as React text children only.
 */
export function ExamQuestions({
  questions,
  sourceTitle,
}: {
  questions: readonly ExamQuestion[];
  /** The document the questions were written from, shown with each answer. */
  sourceTitle: string;
}) {
  const [revealed, setRevealed] = useState<ReadonlySet<number>>(new Set());
  const [chosen, setChosen] = useState<Record<number, string>>({});

  const toggle = (index: number) => {
    setRevealed((current) => {
      const next = new Set(current);

      if (next.has(index)) {
        next.delete(index);
      } else {
        next.add(index);
      }

      return next;
    });
  };

  /* One roving radiogroup per question. The refs let arrow keys move real DOM
   * focus to the newly selected option, not just its tab stop, so a screen
   * reader announces it. This is the focus-and-select model of SectionTabs. */
  const optionRefs = useRef(new Map<string, HTMLButtonElement>());

  const focusAndChoose = (
    questionIndex: number,
    optionIndex: number,
    option: string | undefined,
  ) => {
    if (option === undefined) {
      return;
    }

    optionRefs.current.get(`${questionIndex}:${optionIndex}`)?.focus();
    setChosen((current) => ({ ...current, [questionIndex]: option }));
  };

  return (
    <ol className="space-y-3">
      {questions.map((question, index) => {
        const isRevealed = revealed.has(index);
        const answerId = `exam-answer-${index}`;
        const pick = chosen[index];
        const options = question.options;
        /* The chosen option is the only tab stop, or the first option when
         * nothing is chosen yet, which is the native radio model. */
        const activeOptionIndex =
          options === null
            ? 0
            : Math.max(
                0,
                options.findIndex((option) => option === pick),
              );

        const onOptionKeyDown = (
          event: React.KeyboardEvent<HTMLButtonElement>,
        ) => {
          if (options === null) {
            return;
          }

          const count = options.length;

          switch (event.key) {
            case "ArrowRight":
            case "ArrowDown": {
              event.preventDefault();
              const next = (activeOptionIndex + 1) % count;
              focusAndChoose(index, next, options[next]);
              break;
            }
            case "ArrowLeft":
            case "ArrowUp": {
              event.preventDefault();
              const next = (activeOptionIndex - 1 + count) % count;
              focusAndChoose(index, next, options[next]);
              break;
            }
            case "Home":
              event.preventDefault();
              focusAndChoose(index, 0, options[0]);
              break;
            case "End":
              event.preventDefault();
              focusAndChoose(index, count - 1, options[count - 1]);
              break;
            default:
              break;
          }
        };

        return (
          <li
            key={`${index}-${question.prompt}`}
            className="rounded-lg border border-border-default bg-bg-surface p-4 transition-colors hover:border-border-strong"
          >
            <div className="flex items-start gap-3">
              <span className="mt-0.5 text-caption tabular-nums text-text-muted">
                {String(index + 1).padStart(2, "0")}
              </span>
              <div className="min-w-0 flex-1 space-y-3">
                <p className="whitespace-pre-line text-body-lg text-text-primary">
                  {question.prompt}
                </p>

                {options === null ? (
                  <Badge variant="neutral">Open response</Badge>
                ) : (
                  <ul
                    role="radiogroup"
                    aria-label={`Options for question ${index + 1}`}
                    className="space-y-1.5"
                  >
                    {options.map((option, optionIndex) => {
                      const selected = pick === option;
                      const isAnswer = isRevealed && option === question.answer;

                      return (
                        <li key={`${optionIndex}-${option}`}>
                          <button
                            type="button"
                            role="radio"
                            aria-checked={selected}
                            tabIndex={
                              optionIndex === activeOptionIndex ? 0 : -1
                            }
                            ref={(node) => {
                              const key = `${index}:${optionIndex}`;

                              if (node) {
                                optionRefs.current.set(key, node);
                              } else {
                                optionRefs.current.delete(key);
                              }
                            }}
                            onClick={() =>
                              setChosen((current) => ({
                                ...current,
                                [index]: option,
                              }))
                            }
                            onKeyDown={onOptionKeyDown}
                            className={cn(
                              "flex w-full items-start gap-2.5 rounded-md border px-3 py-2 text-left text-body transition-colors",
                              "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                              isAnswer
                                ? "border-status-success/40 bg-status-success/10 text-text-primary"
                                : selected
                                  ? "border-brand-primary/30 bg-bg-interactive text-text-primary"
                                  : "border-border-default text-text-secondary hover:border-border-strong",
                            )}
                          >
                            <span
                              aria-hidden="true"
                              className="text-caption tabular-nums text-text-muted"
                            >
                              {LETTERS[optionIndex] ?? optionIndex + 1}
                            </span>
                            <span className="min-w-0 flex-1">{option}</span>
                            {isAnswer ? (
                              <Check
                                aria-hidden="true"
                                className="size-4 shrink-0 text-status-success"
                              />
                            ) : null}
                          </button>
                        </li>
                      );
                    })}
                  </ul>
                )}

                <Button
                  variant="secondary"
                  size="sm"
                  aria-expanded={isRevealed}
                  aria-controls={answerId}
                  onClick={() => toggle(index)}
                >
                  {isRevealed ? (
                    <EyeOff aria-hidden="true" className="size-4" />
                  ) : (
                    <Eye aria-hidden="true" className="size-4" />
                  )}
                  {isRevealed ? "Hide answer" : "Reveal answer"}
                </Button>

                {isRevealed ? (
                  <div
                    id={answerId}
                    className="space-y-2 rounded-md border border-border-default bg-bg-subtle p-3 motion-safe:animate-fade-up"
                  >
                    <p className="text-caption text-text-muted">Answer</p>
                    <p className="whitespace-pre-line text-body-lg text-text-primary">
                      {question.answer}
                    </p>
                    <p className="whitespace-pre-line text-body text-text-secondary">
                      {question.explanation}
                    </p>
                    <p className="flex items-start gap-1.5 text-caption text-text-muted">
                      <Info
                        aria-hidden="true"
                        className="mt-0.5 size-3.5 shrink-0"
                      />
                      Written from your own material: {sourceTitle}. It is a
                      practice question, not a prediction of what you will be
                      asked, and it can be wrong. Check it against the source
                      before you rely on it.
                    </p>
                  </div>
                ) : null}
              </div>
            </div>
          </li>
        );
      })}
    </ol>
  );
}
