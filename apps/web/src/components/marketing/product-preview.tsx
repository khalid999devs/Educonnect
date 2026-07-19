import { Badge, cn } from "@educonnect/ui";
import { Brain, CalendarDays, Sparkles } from "lucide-react";
import Image from "next/image";

/**
 * Marketing hero preview: a real screenshot of the EduConnect student
 * dashboard, framed as a browser window. It runs on the same labeled
 * sample-data workspace the Live Demo uses, so the "Sample data" badge stays
 * for honesty — no fabricated numbers.
 */
export function ProductPreview({ className }: { className?: string }) {
  return (
    <div className={cn("relative", className)}>
      <div
        aria-hidden="true"
        className="absolute -inset-8 rounded-[3rem] bg-brand-primary/15 blur-3xl"
      />

      <div className="relative overflow-hidden rounded-lg border border-border-default bg-bg-surface shadow-glow-sm">
        <div className="flex items-center gap-2 border-b border-border-subtle bg-bg-subtle px-4 py-2.5">
          <span aria-hidden="true" className="flex gap-1.5">
            <span className="size-2.5 rounded-full bg-border-strong" />
            <span className="size-2.5 rounded-full bg-border-strong" />
            <span className="size-2.5 rounded-full bg-border-strong" />
          </span>
          <p className="text-caption text-text-muted">
            app.educonnect · dashboard
          </p>
          <Badge variant="neutral" className="ml-auto">
            Sample data
          </Badge>
        </div>

        <Image
          src="/marketing/dashboard.png"
          alt="The EduConnect student dashboard: courses and term, Quick Intake, What's Next, and recommended study tools"
          width={2940}
          height={1550}
          sizes="(max-width: 1024px) 100vw, 620px"
          priority
          className="block h-auto w-full"
        />
      </div>

      <span
        aria-hidden="true"
        className="absolute -left-5 top-16 hidden rounded-md border border-border-default bg-bg-surface p-2.5 shadow-glow-sm motion-safe:animate-float sm:block"
      >
        <Brain className="size-5 text-status-research" />
      </span>
      <span
        aria-hidden="true"
        className="absolute -right-4 top-40 hidden rounded-md border border-border-default bg-bg-surface p-2.5 shadow-glow-sm motion-safe:animate-float-delayed sm:block"
      >
        <Sparkles className="size-5 text-status-ai" />
      </span>
      <span
        aria-hidden="true"
        className="absolute -bottom-4 left-10 hidden rounded-md border border-border-default bg-bg-surface p-2.5 shadow-glow-sm motion-safe:animate-float sm:block"
      >
        <CalendarDays className="size-5 text-brand-primary" />
      </span>
    </div>
  );
}
