import {
  buttonClasses,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@educonnect/ui";
import { BookOpen, Play } from "lucide-react";
import type { Metadata } from "next";
import Link from "next/link";

import { ContactForm } from "@/components/marketing/contact-form";

export const metadata: Metadata = {
  title: "Contact",
  description:
    "How to reach EduConnect. Support channels open at public launch; the Live Demo and blog are available now.",
  openGraph: {
    title: "Contact EduConnect",
    description:
      "Support channels open at public launch; the Live Demo and blog are available now.",
    type: "website",
  },
};

export default function ContactPage() {
  return (
    <div className="relative mx-auto w-full max-w-6xl overflow-x-clip px-6 pb-24 pt-14 lg:pt-18">
      <div
        aria-hidden="true"
        className="absolute -top-24 left-1/3 h-56 w-96 rounded-full bg-brand-primary/10 blur-3xl"
      />

      <header className="relative max-w-2xl space-y-4">
        <h1 className="text-display text-text-primary motion-safe:animate-fade-up">
          Contact
        </h1>
        <p className="text-body-lg text-text-secondary motion-safe:animate-fade-up motion-safe:[animation-delay:100ms]">
          EduConnect is pre-launch, so we're honest about what's open: a
          monitored support channel arrives with registration. Here's the form
          it will use, and what you can explore in the meantime.
        </p>
      </header>

      <div className="relative mt-10 grid gap-8 lg:grid-cols-[1.15fr_0.85fr]">
        <Card className="scroll-reveal p-6 lg:p-8">
          <ContactForm />
        </Card>

        <div className="space-y-4">
          <Card className="scroll-reveal">
            <CardHeader className="mb-2">
              <CardTitle as="h2">In the meantime</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <p>
                The Live Demo answers most "what does it actually do?" questions
                in about two minutes, and the blog covers how the product works
                and the principles behind it.
              </p>
              <div className="flex flex-col gap-2.5">
                <Link
                  href="/demo"
                  className={buttonClasses({ fullWidth: true })}
                >
                  <Play aria-hidden="true" className="size-4" />
                  Try the Live Demo
                </Link>
                <Link
                  href="/blog"
                  className={buttonClasses({
                    variant: "secondary",
                    fullWidth: true,
                  })}
                >
                  <BookOpen aria-hidden="true" className="size-4" />
                  Read the blog
                </Link>
              </div>
            </CardContent>
          </Card>

          <Card className="scroll-reveal bg-bg-interactive">
            <CardContent>
              <p className="text-body text-text-primary">
                Why no email address here? Because we won't publish a channel
                nobody monitors yet. When support opens, this page gets the real
                thing.
              </p>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}
