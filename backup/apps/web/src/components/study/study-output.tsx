"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@educonnect/ui";
import { Lightbulb } from "lucide-react";

import { IconChip } from "@/components/shared/icon-chip";
import type { StudyOutputPayload } from "@/lib/api/study";

/**
 * Renders a summary, topic explanation, or quick-learn result.
 *
 * Every string here originated with a language model reading a document that
 * the student did not write, so all of it is rendered as React text children.
 * Nothing is passed to `dangerouslySetInnerHTML`, and no field is parsed as
 * markdown, so an injection payload in the source document renders as inert
 * escaped text.
 */
export function StudyOutput({ payload }: { payload: StudyOutputPayload }) {
  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <h3 className="text-h3 text-text-primary">{payload.title}</h3>
        <p className="whitespace-pre-line text-body-lg text-text-secondary">
          {payload.overview}
        </p>
      </div>

      {payload.sections.length > 0 ? (
        <ol className="space-y-3">
          {payload.sections.map((section, index) => (
            <li
              key={`${index}-${section.heading}`}
              className="rounded-lg border border-border-default bg-bg-surface p-4 transition-colors hover:border-border-strong"
            >
              <div className="flex items-baseline gap-3">
                <span className="text-caption tabular-nums text-text-muted">
                  {String(index + 1).padStart(2, "0")}
                </span>
                <h4 className="text-h4 text-text-primary">{section.heading}</h4>
              </div>
              <p className="mt-2 whitespace-pre-line text-body text-text-secondary">
                {section.body}
              </p>
            </li>
          ))}
        </ol>
      ) : null}

      {payload.key_points.length > 0 ? (
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <IconChip icon={Lightbulb} accent="study" size="sm" />
              Worth remembering
            </CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="space-y-2">
              {payload.key_points.map((point, index) => (
                <li
                  key={`${index}-${point}`}
                  className="flex gap-3 text-body text-text-secondary"
                >
                  <span
                    aria-hidden="true"
                    className="mt-2 size-1.5 shrink-0 rounded-full bg-status-ai"
                  />
                  {point}
                </li>
              ))}
            </ul>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
