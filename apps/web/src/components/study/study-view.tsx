"use client";

import { Alert, Button, cn } from "@educonnect/ui";
import {
  BookOpenCheck,
  FolderOpen,
  Link2,
  Sparkles,
  UploadCloud,
} from "lucide-react";
import { useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { PageCover } from "@/components/shared/page-cover";

import { IntentPicker } from "./intent-picker";
import { StudyCapture } from "./study-capture";
import { StudyGeneration } from "./study-generation";
import { findStudyIntent, type StudyIntent } from "./study-intents";
import { StudyLibraryPicker } from "./study-library-picker";

type MaterialSource = "link" | "file" | "library";

type StudySource = { itemId: string; title: string };

/**
 * The Study section.
 *
 * The whole flow - intent, material, generation, result - is one scrolling
 * page. There is no dialog, drawer, or modal anywhere in it: choosing an
 * intent collapses the picker in place, the walkthrough replaces its own panel
 * contents, and results expand below the actions that produced them.
 */
export function StudyView() {
  const [intent, setIntent] = useState<StudyIntent | null>(null);
  const [intentOpen, setIntentOpen] = useState(true);
  const [material, setMaterial] = useState<MaterialSource>("link");
  const [source, setSource] = useState<StudySource | null>(null);
  const [captureNote, setCaptureNote] = useState<string | null>(null);
  const [adoptedIntakeId, setAdoptedIntakeId] = useState<string | null>(null);

  const config = intent === null ? null : findStudyIntent(intent);

  return (
    <div className="space-y-4">
      <PageCover
        photo="/marketing/study-desk.jpg"
        headingLevel={1}
        tall
        priority
        title="Study, learn, or prepare for an exam"
        subtitle="Bring in your own material and turn it into a summary, an explanation, a walkthrough, or practice questions. Everything is generated from the document you choose, and a generation that fails says so."
      />

      <section
        aria-labelledby="study-intent-heading"
        className="space-y-3 motion-safe:animate-fade-up"
      >
        <div className="flex items-center gap-2">
          <IconChip icon={Sparkles} accent="study" size="sm" />
          <h2 id="study-intent-heading" className="text-h4 text-text-primary">
            What are you here to do?
          </h2>
        </div>
        <IntentPicker
          value={intent}
          expanded={intentOpen}
          onReopen={() => setIntentOpen(true)}
          onChange={(next) => {
            setIntent(next);
            setIntentOpen(false);
            setMaterial(next === "exam" ? "library" : "link");
          }}
        />
      </section>

      {config === null ? null : (
        <>
          <section
            aria-labelledby="study-material-heading"
            className="space-y-3 motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]"
          >
            <div className="flex flex-wrap items-center justify-between gap-2">
              <div className="flex items-center gap-2">
                <IconChip icon={FolderOpen} accent="study" size="sm" />
                <h2
                  id="study-material-heading"
                  className="text-h4 text-text-primary"
                >
                  {config.materialTitle}
                </h2>
              </div>
              {source ? (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => {
                    setSource(null);
                    setCaptureNote(null);
                    setAdoptedIntakeId(null);
                  }}
                >
                  Choose different material
                </Button>
              ) : null}
            </div>

            {source ? (
              <div className="flex items-center gap-3 rounded-lg border border-status-ai/30 bg-bg-surface p-3">
                <IconChip icon={BookOpenCheck} accent="study" size="sm" />
                <p className="min-w-0 flex-1 truncate text-label text-text-primary">
                  {source.title}
                </p>
              </div>
            ) : (
              <>
                <div
                  role="group"
                  aria-label="Where your material comes from"
                  className="flex flex-wrap items-center gap-2"
                >
                  {(
                    [
                      { value: "link", label: "Paste a link", icon: Link2 },
                      {
                        value: "file",
                        label: "Upload a file",
                        icon: UploadCloud,
                      },
                      {
                        value: "library",
                        label: "From my library",
                        icon: FolderOpen,
                      },
                    ] as const
                  ).map((option) => (
                    <button
                      key={option.value}
                      type="button"
                      aria-pressed={material === option.value}
                      onClick={() => setMaterial(option.value)}
                      className={cn(
                        "flex items-center gap-2 rounded-md border px-3 py-1.5 text-button transition-colors",
                        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus",
                        material === option.value
                          ? "border-status-ai/40 bg-bg-interactive text-text-primary"
                          : "border-border-default text-text-muted hover:border-border-strong hover:text-text-primary",
                      )}
                    >
                      <option.icon aria-hidden="true" className="size-4" />
                      {option.label}
                    </button>
                  ))}
                </div>

                {captureNote ? (
                  <Alert variant="info" title="Capturing first">
                    {captureNote}
                  </Alert>
                ) : null}

                {material === "library" ? (
                  <StudyLibraryPicker
                    onReady={(ready) => {
                      setSource(ready);
                      setCaptureNote(null);
                    }}
                    onNeedsCapture={(intakeId, resourceTitle) => {
                      setAdoptedIntakeId(intakeId);
                      setMaterial("file");
                      setCaptureNote(
                        `"${resourceTitle}" has not been read yet, so there is no text to study from. Its capture has been started - review it below and save it, then the study actions will appear.`,
                      );
                    }}
                  />
                ) : (
                  /* Keyed on the adopted capture id, not the mode: switching
                     link and file keeps the panel, but adopting a library
                     item's capture remounts it as that item's initial state. */
                  <StudyCapture
                    key={adoptedIntakeId ?? "new-capture"}
                    mode={material}
                    initialIntakeId={adoptedIntakeId}
                    onReady={(ready) => {
                      setSource(ready);
                      setCaptureNote(null);
                    }}
                  />
                )}
              </>
            )}
          </section>

          {source ? (
            <section
              aria-labelledby="study-generate-heading"
              className="space-y-3 motion-safe:animate-fade-up motion-safe:[animation-delay:160ms]"
            >
              <h2 id="study-generate-heading" className="sr-only">
                Generate study material
              </h2>
              <StudyGeneration intent={config.value} source={source} />
            </section>
          ) : null}
        </>
      )}
    </div>
  );
}
