"use client";

import { Badge, buttonClasses, Card, CardContent } from "@educonnect/ui";
import { BadgeCheck } from "lucide-react";
import Link from "next/link";

import type { MentorProfile } from "@/lib/api/mentors";

export function MentorCard({ mentor }: { mentor: MentorProfile }) {
  const initial = mentor.name.trim().charAt(0).toUpperCase() || "?";

  return (
    <Card className="h-full">
      <CardContent className="flex h-full flex-col gap-3 p-5">
        <div className="flex items-start gap-3">
          <span
            aria-hidden
            className="flex size-11 shrink-0 items-center justify-center rounded-full bg-bg-interactive text-body font-semibold text-text-secondary"
          >
            {initial}
          </span>
          <div className="min-w-0">
            <span className="flex items-center gap-2 font-semibold text-text-primary">
              {mentor.name}
              {mentor.verification_state === "verified" ? (
                <Badge variant="success">
                  <BadgeCheck className="size-3.5" aria-hidden />
                  Verified
                </Badge>
              ) : null}
            </span>
            <p className="text-body text-text-secondary">{mentor.headline}</p>
          </div>
        </div>

        {mentor.expertise.length > 0 ? (
          <ul className="flex flex-wrap gap-1.5">
            {mentor.expertise.slice(0, 6).map((tag) => (
              <li key={tag}>
                <Badge variant="neutral">{tag}</Badge>
              </li>
            ))}
          </ul>
        ) : null}

        {mentor.availability_note ? (
          <p className="text-caption text-text-muted">
            {mentor.availability_note}
          </p>
        ) : null}

        <div className="mt-auto pt-1">
          <Link
            href={`/mentors/${mentor.id}`}
            className={buttonClasses({
              variant: "secondary",
              size: "sm",
              fullWidth: true,
            })}
          >
            View profile
          </Link>
        </div>
      </CardContent>
    </Card>
  );
}
