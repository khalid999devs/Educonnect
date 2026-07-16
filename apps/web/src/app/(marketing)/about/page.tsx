import { Badge, buttonClasses } from "@educonnect/ui";
import {
  CalendarDays,
  GraduationCap,
  Globe,
  Heart,
  Play,
  ShieldCheck,
  Sparkles,
  Users,
} from "lucide-react";
import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";

import { Eyebrow } from "@/components/marketing/eyebrow";
import { CapIllustration } from "@/components/marketing/illustrations";

export const metadata: Metadata = {
  title: "About",
  description:
    "Why EduConnect exists: the scattered-tools problem, the guided loop we're building, the principles we won't trade away, and exactly where the product stands today.",
  openGraph: {
    title: "About EduConnect",
    description:
      "The scattered-tools problem, the guided loop, the principles we won't trade away, and where the product stands today.",
    type: "website",
  },
};

const PILLARS = [
  {
    icon: GraduationCap,
    title: "Learn smarter",
    description:
      "Goal-based tools, editable prompts, and workflow recipes that explain their reasoning — so you always know why, not just what.",
  },
  {
    icon: CalendarDays,
    title: "Stay organized",
    description:
      "Courses, tasks, deadlines, files, and knowledge in one private workspace that composes your day from real records.",
  },
  {
    icon: Users,
    title: "Grow together",
    description:
      "Curated communities and mentor help join the platform as the MVP completes — built deliberately, not bolted on.",
  },
] as const;

const VALUES = [
  {
    icon: Heart,
    title: "Student first",
    description:
      "Every decision is guided by what helps students learn, plan, and finish — never by engagement metrics.",
  },
  {
    icon: ShieldCheck,
    title: "Trust & privacy",
    description:
      "Private by default: your academic content stays yours, with signed access and no browsing by anyone else.",
  },
  {
    icon: Sparkles,
    title: "Honest AI",
    description:
      "AI assists and is always labeled. Suggestions come with reasons, and nothing is created without your review.",
  },
  {
    icon: Globe,
    title: "Truth over theater",
    description:
      "Real numbers with explicit timeframes — in the product and in this website's marketing. No invented anything.",
  },
] as const;

export default function AboutPage() {
  return (
    <div className="overflow-x-clip">
      {/* Hero */}
      <section
        aria-labelledby="about-title"
        className="relative mx-auto w-full max-w-6xl px-6 pb-16 pt-14 lg:pt-20"
      >
        <div
          aria-hidden="true"
          className="absolute -top-28 right-10 h-64 w-96 rounded-full bg-brand-primary/15 blur-3xl"
        />
        <div className="relative grid items-center gap-12 lg:grid-cols-[1.1fr_0.9fr]">
          <div className="max-w-xl space-y-6">
            <Badge variant="brand" className="motion-safe:animate-fade-up">
              About EduConnect
            </Badge>
            <h1
              id="about-title"
              className="text-display text-text-primary motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]"
            >
              Empowering every{" "}
              <span className="text-brand-primary">university journey</span>
            </h1>
            <p className="text-body-lg text-text-secondary motion-safe:animate-fade-up motion-safe:[animation-delay:160ms]">
              EduConnect helps university students succeed at every stage — from
              getting oriented, through daily coursework, to research and longer
              plans. This page tells you what it is, what it refuses to be, and
              exactly where it stands.
            </p>
            <div className="flex flex-wrap gap-3 motion-safe:animate-fade-up motion-safe:[animation-delay:240ms]">
              <Link
                href="/demo"
                className={buttonClasses({ size: "lg", glow: true })}
              >
                <Play aria-hidden="true" className="size-4" />
                Try the Live Demo
              </Link>
            </div>
          </div>

          <div className="relative hidden lg:block motion-safe:animate-fade-in">
            <div
              aria-hidden="true"
              className="absolute -inset-6 rounded-[2.5rem] bg-brand-primary/15 blur-3xl"
            />
            <div className="relative overflow-hidden rounded-xl border border-border-default shadow-glow-sm">
              <Image
                src="/marketing/library-curve.jpg"
                alt="Curved library shelves filled with books"
                width={960}
                height={638}
                sizes="(max-width: 1280px) 45vw, 520px"
                className="h-72 w-full object-cover"
                priority
              />
              <div
                aria-hidden="true"
                className="absolute inset-0 bg-linear-to-t from-bg-canvas/60 via-transparent to-transparent"
              />
            </div>
            <div className="absolute -bottom-8 -left-8 w-36">
              <CapIllustration />
            </div>
          </div>
        </div>
      </section>

      {/* Pillars */}
      <section
        aria-labelledby="pillars-title"
        className="mx-auto w-full max-w-6xl space-y-8 px-6 py-16"
      >
        <div className="scroll-reveal mx-auto max-w-2xl space-y-3 text-center">
          <Eyebrow>Our core vision</Eyebrow>
          <h2 id="pillars-title" className="text-h2 text-text-primary">
            Three pillars. One mission.
          </h2>
        </div>
        <ul className="grid gap-4 md:grid-cols-3">
          {PILLARS.map((pillar) => (
            <li
              key={pillar.title}
              className="scroll-reveal flex gap-4 rounded-lg border border-border-default bg-bg-surface p-6 transition-all duration-300 hover:-translate-y-1 hover:border-border-strong"
            >
              <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-bg-interactive">
                <pillar.icon
                  aria-hidden="true"
                  className="size-5 text-brand-primary"
                />
              </span>
              <span>
                <span className="block text-h4 text-text-primary">
                  {pillar.title}
                </span>
                <span className="mt-1 block text-body text-text-secondary">
                  {pillar.description}
                </span>
              </span>
            </li>
          ))}
        </ul>
      </section>

      {/* Story and values */}
      <section
        aria-labelledby="story-title"
        className="border-y border-border-subtle bg-bg-surface/60"
      >
        <div className="mx-auto grid w-full max-w-6xl gap-12 px-6 py-16 lg:grid-cols-2">
          <div className="scroll-reveal space-y-4">
            <Eyebrow>The problem</Eyebrow>
            <h2 id="story-title" className="text-h2 text-text-primary">
              Scattered tools.
              <br />
              Scattered semesters.
            </h2>
            <p className="text-body-lg text-text-secondary">
              Students operate across files, LMS pages, messaging groups, AI
              tools, calendars, templates, and research links. Most know that
              helpful tools exist — far fewer know which one fits the task in
              front of them, how to use it responsibly, where to store the
              result, or what to do next.
            </p>
            <p className="text-body-lg text-text-secondary">
              EduConnect closes the gap between knowing and doing: identify the
              goal, get the right tool with the reasoning attached, capture and
              organize the material, and end every path with a destination — the
              course, task, or research topic where the result belongs.
            </p>
          </div>
          <div className="scroll-reveal space-y-5">
            <Eyebrow>Our values</Eyebrow>
            <ul className="space-y-5">
              {VALUES.map((value) => (
                <li key={value.title} className="flex gap-4">
                  <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-bg-interactive">
                    <value.icon
                      aria-hidden="true"
                      className="size-5 text-brand-primary"
                    />
                  </span>
                  <span>
                    <span className="block text-body font-semibold text-text-primary">
                      {value.title}
                    </span>
                    <span className="mt-0.5 block text-body text-text-secondary">
                      {value.description}
                    </span>
                  </span>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </section>

      {/* Status */}
      <section
        aria-labelledby="status-title"
        className="mx-auto w-full max-w-6xl px-6 py-16"
      >
        <div className="scroll-reveal rounded-lg border border-border-default bg-bg-surface p-8 lg:p-10">
          <Eyebrow>Where it stands today</Eyebrow>
          <h2 id="status-title" className="mt-2 text-h3 text-text-primary">
            In development, and honest about it
          </h2>
          <p className="mt-3 max-w-3xl text-body-lg text-text-secondary">
            The product backend — accounts, onboarding, courses, tasks and
            planner, private file storage, the tools/prompts/workflow guide,
            templates, Smart Intake with review-first extraction, the Second
            Brain, and the truthful dashboard — is built and tested. The student
            and admin interfaces are being assembled on a shared design system
            now. Registration opens when the product interface is complete;
            until then, the Live Demo shows the real loop with clearly labeled
            sample data.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Link
              href="/contact"
              className={buttonClasses({ variant: "secondary" })}
            >
              Contact
            </Link>
            <Link href="/blog" className={buttonClasses({ variant: "ghost" })}>
              Follow along on the blog
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
