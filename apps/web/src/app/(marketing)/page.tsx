import { Badge, buttonClasses } from "@educonnect/ui";
import {
  ArrowRight,
  Brain,
  CalendarDays,
  CircleCheck,
  Compass,
  Inbox,
  LayoutTemplate,
  Lock,
  Play,
  ShieldCheck,
  Sparkles,
  TrendingUp,
} from "lucide-react";
import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";

import { Eyebrow } from "@/components/marketing/eyebrow";
import { CapIllustration } from "@/components/marketing/illustrations";
import { ProductPreview } from "@/components/marketing/product-preview";

export const metadata: Metadata = {
  description:
    "Your university life, organized in one smart platform. EduConnect guides students from need to action — the right tool, organized material, truthful progress. Try the Live Demo, no signup.",
  openGraph: {
    title: "EduConnect — your university life, organized in one smart platform",
    description:
      "Plan smarter, learn faster, and stay on track with guided tools, Quick Intake, and truthful progress. Try the Live Demo without an account.",
    type: "website",
  },
};

const TRUST_POINTS = [
  { icon: CircleCheck, label: "Plan smarter" },
  { icon: CircleCheck, label: "Learn faster" },
  { icon: CircleCheck, label: "Stay honest with yourself" },
] as const;

const HOW_IT_WORKS = [
  {
    title: "Tell EduConnect your goal",
    description:
      "Start from what you actually need to do — write a literature review, plan exam week, summarize a lecture.",
  },
  {
    title: "Get tools, prompts, and workflows with reasons",
    description:
      "Curated recommendations explain why a tool fits, how to use it responsibly, and what to do with the result.",
  },
  {
    title: "Capture material with Quick Intake",
    description:
      "Upload a syllabus or paste a link. Extracted deadlines and topics arrive as suggestions you review — nothing saves itself.",
  },
  {
    title: "See today's plan and honest progress",
    description:
      "One dashboard with your next action, real deadlines, and progress computed from what you actually did.",
  },
] as const;

const FEATURES = [
  {
    icon: Inbox,
    title: "Smart Intake",
    description:
      "Capture and organize everything in one place — suggestions with reasons, confirmed by you.",
  },
  {
    icon: Brain,
    title: "Academic Second Brain",
    description:
      "Connect ideas, notes, and sources effortlessly, with search that finds them again.",
  },
  {
    icon: Sparkles,
    title: "AI tools & templates",
    description:
      "Goal-based guidance with editable prompts, workflow recipes, and independent template copies.",
  },
  {
    icon: CalendarDays,
    title: "Planner & courses",
    description:
      "Terms, deadlines, and focus sessions in one private workspace, in your timezone.",
  },
  {
    icon: TrendingUp,
    title: "Truthful progress",
    description:
      "Real records in an explicit timeframe. Empty weeks say so — no streak tricks.",
  },
  {
    icon: Compass,
    title: "Guided, not overwhelming",
    description:
      "Every recommendation carries its reasoning and ends with a destination for the result.",
  },
] as const;

const PRINCIPLES = [
  {
    icon: ShieldCheck,
    title: "Academic integrity first",
    description:
      "Guidance explains responsible use. AI-assisted content is always labeled, and nothing submits work on your behalf.",
  },
  {
    icon: Lock,
    title: "Private by default",
    description:
      "Your courses, files, and notes are yours alone — private storage, signed access, no sharing without you.",
  },
  {
    icon: Sparkles,
    title: "No fabricated numbers",
    description:
      "No fake testimonials, invented user counts, or motivational math — on this site or inside the product.",
  },
] as const;

export default function LandingPage() {
  return (
    <div className="overflow-x-clip">
      {/* Hero */}
      <section
        aria-labelledby="hero-title"
        className="relative mx-auto w-full max-w-6xl px-6 pb-20 pt-14 lg:pt-20"
      >
        <div
          aria-hidden="true"
          className="absolute -top-32 left-1/2 h-72 w-144 -translate-x-1/2 rounded-full bg-brand-primary/15 blur-3xl"
        />
        <div
          aria-hidden="true"
          className="absolute inset-x-0 -top-10 h-112 bg-[radial-gradient(var(--border-strong)_1px,transparent_1px)] [background-size:26px_26px] opacity-30 [mask-image:radial-gradient(ellipse_65%_70%_at_50%_30%,black,transparent)]"
        />
        <div className="relative grid items-center gap-14 lg:grid-cols-[1.05fr_0.95fr]">
          <div className="max-w-xl space-y-6">
            <Badge variant="brand" className="motion-safe:animate-fade-up">
              All-in-one platform for university students · in development
            </Badge>
            <h1
              id="hero-title"
              className="text-display text-text-primary motion-safe:animate-fade-up motion-safe:[animation-delay:80ms] lg:text-display-xl"
            >
              Your university life, organized in one{" "}
              <span className="text-brand-primary">smart platform</span>.
            </h1>
            <p className="text-body-lg text-text-secondary motion-safe:animate-fade-up motion-safe:[animation-delay:160ms]">
              Plan smarter, learn faster, and stay on track with guided AI
              tools, organized resources, and progress that never lies to you.
            </p>
            <div className="flex flex-wrap items-center gap-3 motion-safe:animate-fade-up motion-safe:[animation-delay:240ms]">
              <Link
                href="/demo"
                className={buttonClasses({ size: "lg", glow: true })}
              >
                <Play aria-hidden="true" className="size-4" />
                Try the Live Demo
              </Link>
              <Link
                href="/about"
                className={buttonClasses({ variant: "secondary", size: "lg" })}
              >
                Read the story
              </Link>
            </div>
            <ul className="flex flex-wrap gap-x-6 gap-y-2 motion-safe:animate-fade-up motion-safe:[animation-delay:320ms]">
              {TRUST_POINTS.map((point) => (
                <li
                  key={point.label}
                  className="flex items-center gap-1.5 text-body text-text-secondary"
                >
                  <point.icon
                    aria-hidden="true"
                    className="size-4 text-status-success"
                  />
                  {point.label}
                </li>
              ))}
            </ul>
            <p className="text-caption text-text-muted">
              No signup — the demo runs in your browser on labeled sample data.
            </p>
          </div>

          <div className="motion-safe:animate-fade-in motion-safe:[animation-delay:200ms]">
            <ProductPreview />
          </div>
        </div>
      </section>

      {/* How it works */}
      <section
        id="how-it-works"
        aria-labelledby="how-title"
        className="mx-auto w-full max-w-6xl space-y-8 px-6 py-20"
      >
        <div className="scroll-reveal mx-auto max-w-2xl space-y-3 text-center">
          <Eyebrow>The loop</Eyebrow>
          <h2 id="how-title" className="text-h2 text-text-primary">
            From need to action, every day
          </h2>
          <p className="text-body-lg text-text-secondary">
            One guided loop designed around how studying actually happens.
          </p>
        </div>
        <ol className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          {HOW_IT_WORKS.map((step, index) => (
            <li
              key={step.title}
              className="scroll-reveal rounded-lg border border-border-default bg-bg-surface p-6 transition-all duration-300 hover:-translate-y-1 hover:border-border-strong"
            >
              <p className="flex size-9 items-center justify-center rounded-md bg-bg-interactive text-body font-semibold tabular-nums text-brand-primary">
                {index + 1}
              </p>
              <h3 className="mt-4 text-h4 text-text-primary">{step.title}</h3>
              <p className="mt-2 text-body text-text-secondary">
                {step.description}
              </p>
            </li>
          ))}
        </ol>
      </section>

      {/* Features */}
      <section
        id="features"
        aria-labelledby="features-title"
        className="border-y border-border-subtle bg-bg-surface/60"
      >
        <div className="mx-auto w-full max-w-6xl space-y-8 px-6 py-20">
          <div className="scroll-reveal mx-auto max-w-2xl space-y-3 text-center">
            <Eyebrow>Powerful features</Eyebrow>
            <h2 id="features-title" className="text-h2 text-text-primary">
              Everything you need to succeed
            </h2>
            <p className="text-body-lg text-text-secondary">
              Every capability below is built and walkable in the Live Demo —
              nothing here is a mockup promise.
            </p>
          </div>
          <ul className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {FEATURES.map((feature) => (
              <li
                key={feature.title}
                className="scroll-reveal group rounded-lg border border-border-default bg-bg-surface p-6 text-center transition-all duration-300 hover:-translate-y-1 hover:border-brand-primary/40"
              >
                <span className="mx-auto flex size-14 items-center justify-center rounded-full bg-bg-interactive transition-[box-shadow,transform] duration-300 group-hover:scale-110 group-hover:shadow-glow-sm">
                  <feature.icon
                    aria-hidden="true"
                    className="size-6 text-brand-primary"
                  />
                </span>
                <h3 className="mt-4 text-h4 text-text-primary">
                  {feature.title}
                </h3>
                <p className="mt-2 text-body text-text-secondary">
                  {feature.description}
                </p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Command center */}
      <section
        aria-labelledby="command-title"
        className="mx-auto w-full max-w-6xl px-6 py-20"
      >
        <div className="grid items-center gap-14 lg:grid-cols-[0.9fr_1.1fr]">
          <div className="scroll-reveal max-w-xl space-y-5">
            <Eyebrow>Your command center</Eyebrow>
            <h2 id="command-title" className="text-h2 text-text-primary">
              Built to guide students{" "}
              <span className="text-brand-primary">every day</span>
            </h2>
            <p className="text-body-lg text-text-secondary">
              From planning and tasks to AI tools and resources — everything you
              need in one intelligent dashboard, composed from your real records
              at the moment you open it.
            </p>
            <ul className="space-y-2.5">
              {[
                "One Quick Intake module — never two competing upload surfaces",
                "What's Next always points at a real task or review item",
                "Progress you can trust, with an explicit timeframe",
              ].map((line) => (
                <li
                  key={line}
                  className="flex items-start gap-2 text-body text-text-secondary"
                >
                  <CircleCheck
                    aria-hidden="true"
                    className="mt-0.5 size-4 shrink-0 text-status-success"
                  />
                  {line}
                </li>
              ))}
            </ul>
            <Link
              href="/demo"
              className="inline-flex items-center gap-1 text-body font-medium text-brand-primary hover:underline"
            >
              See it live in the demo
              <ArrowRight aria-hidden="true" className="size-4" />
            </Link>
          </div>
          <div className="scroll-reveal relative">
            <div
              aria-hidden="true"
              className="absolute -inset-6 rounded-[2.5rem] bg-brand-primary/15 blur-3xl"
            />
            <div className="relative overflow-hidden rounded-xl border border-border-default shadow-glow-sm">
              <Image
                src="/marketing/study-desk.jpg"
                alt="A calm study desk with a laptop and an open notebook"
                width={960}
                height={640}
                sizes="(max-width: 1024px) 100vw, 620px"
                className="w-full object-cover"
              />
              <div
                aria-hidden="true"
                className="absolute inset-0 bg-linear-to-t from-bg-canvas/70 via-transparent to-transparent"
              />
              <p className="absolute bottom-4 left-4 rounded-full border border-border-default bg-bg-surface/90 px-3 py-1 text-caption text-text-secondary backdrop-blur-sm">
                One calm place for the whole semester
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* Principles */}
      <section
        aria-labelledby="principles-title"
        className="mx-auto w-full max-w-6xl px-6 pb-20"
      >
        <div className="scroll-reveal rounded-lg border border-border-default bg-bg-surface p-8 lg:p-10">
          <Eyebrow>Instead of vanity numbers</Eyebrow>
          <h2 id="principles-title" className="mt-2 text-h3 text-text-primary">
            Built on three promises
          </h2>
          <ul className="mt-6 grid gap-6 md:grid-cols-3">
            {PRINCIPLES.map((principle) => (
              <li key={principle.title} className="space-y-2">
                <span className="flex size-10 items-center justify-center rounded-md bg-bg-interactive">
                  <principle.icon
                    aria-hidden="true"
                    className="size-5 text-brand-primary"
                  />
                </span>
                <p className="text-h4 text-text-primary">{principle.title}</p>
                <p className="text-body text-text-secondary">
                  {principle.description}
                </p>
              </li>
            ))}
          </ul>
        </div>
      </section>

      {/* Final CTA */}
      <section
        aria-labelledby="cta-title"
        className="mx-auto w-full max-w-6xl px-6 pb-24"
      >
        <div className="scroll-reveal relative overflow-hidden rounded-xl border border-brand-primary/30 bg-bg-interactive px-6 py-16 text-center">
          <div
            aria-hidden="true"
            className="absolute -top-24 left-1/2 h-64 w-120 -translate-x-1/2 rounded-full bg-brand-primary/25 blur-3xl"
          />
          <div className="relative flex flex-col items-center gap-5">
            <CapIllustration className="w-44" />
            <Eyebrow>Ready when you are</Eyebrow>
            <h2 id="cta-title" className="max-w-2xl text-h2 text-text-primary">
              Study smarter with EduConnect
            </h2>
            <p className="max-w-xl text-body-lg text-text-secondary">
              All the tools. All your courses. All in one place. Walk the full
              loop in about two minutes — in your browser, on labeled sample
              data.
            </p>
            <div className="mt-1 flex flex-wrap items-center justify-center gap-3">
              <Link
                href="/demo"
                className={buttonClasses({ size: "lg", glow: true })}
              >
                <Play aria-hidden="true" className="size-4" />
                Open the Live Demo
              </Link>
              <Link
                href="/blog"
                className={buttonClasses({ variant: "secondary", size: "lg" })}
              >
                Read the blog
              </Link>
            </div>
          </div>
        </div>
      </section>

      {/* Roadmap honesty strip */}
      <section
        aria-label="Coming later"
        className="mx-auto w-full max-w-6xl px-6 pb-20"
      >
        <p className="scroll-reveal flex flex-wrap items-center justify-center gap-2 text-center text-caption text-text-muted">
          <LayoutTemplate aria-hidden="true" className="size-3.5" />
          Curated communities, mentor help, and registration arrive as the MVP
          completes — nothing on this page pretends otherwise.
        </p>
      </section>
    </div>
  );
}
