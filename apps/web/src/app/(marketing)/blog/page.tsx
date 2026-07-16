import { Badge, buttonClasses, cn } from "@educonnect/ui";
import {
  Compass,
  Inbox,
  ShieldCheck,
  Star,
  type LucideIcon,
} from "lucide-react";
import type { Metadata } from "next";
import Image from "next/image";
import Link from "next/link";

import { Eyebrow } from "@/components/marketing/eyebrow";
import { BookIllustration } from "@/components/marketing/illustrations";
import {
  BLOG_POSTS,
  formatPostDate,
  type BlogCategory,
  type BlogPost,
} from "@/content/blog";

export const metadata: Metadata = {
  title: "Blog",
  description:
    "Notes from building EduConnect: product decisions, how features work, and the principles behind them.",
  openGraph: {
    title: "EduConnect blog",
    description:
      "Notes from building EduConnect: product decisions, how features work, and the principles behind them.",
    type: "website",
  },
};

const CATEGORY_ICONS: Record<BlogCategory, LucideIcon> = {
  Product: Compass,
  Principles: ShieldCheck,
  "How it works": Inbox,
};

const CATEGORY_TINTS: Record<BlogCategory, string> = {
  Product: "text-brand-primary",
  Principles: "text-status-ai",
  "How it works": "text-status-research",
};

function CoverPanel({
  post,
  className,
  sizes,
}: {
  post: BlogPost;
  className?: string;
  sizes: string;
}) {
  const Icon = CATEGORY_ICONS[post.category];

  return (
    <div
      aria-hidden="true"
      className={cn(
        "relative overflow-hidden rounded-md border border-border-subtle",
        className,
      )}
    >
      <Image
        src={post.cover}
        alt=""
        fill
        sizes={sizes}
        className="object-cover transition-transform duration-500 group-hover:scale-105"
      />
      <span className="absolute inset-0 bg-linear-to-t from-bg-canvas/70 via-transparent to-transparent" />
      <span className="absolute bottom-3 left-3 flex size-10 items-center justify-center rounded-md border border-border-default bg-bg-surface/90 shadow-glow-sm backdrop-blur-sm">
        <Icon className={cn("size-5", CATEGORY_TINTS[post.category])} />
      </span>
    </div>
  );
}

export default function BlogIndexPage() {
  const posts = [...BLOG_POSTS].sort((a, b) =>
    b.publishedAt.localeCompare(a.publishedAt),
  );
  const [featured, ...rest] = posts;

  return (
    <div className="overflow-x-clip">
      {/* Hero band */}
      <section
        aria-labelledby="blog-title"
        className="relative mx-auto w-full max-w-6xl px-6 pb-12 pt-14 lg:pt-18"
      >
        <div
          aria-hidden="true"
          className="absolute -top-24 right-16 h-56 w-80 rounded-full bg-brand-primary/15 blur-3xl"
        />
        <div className="relative flex flex-wrap items-center justify-between gap-10">
          <div className="max-w-xl space-y-4">
            <h1
              id="blog-title"
              className="text-display text-text-primary motion-safe:animate-fade-up"
            >
              Blog
            </h1>
            <p className="text-body-lg text-text-secondary motion-safe:animate-fade-up motion-safe:[animation-delay:100ms]">
              Notes from building EduConnect — how features work, the decisions
              behind them, and the principles we hold while shipping.
            </p>
          </div>
          <div className="hidden w-60 shrink-0 md:block motion-safe:animate-fade-in">
            <BookIllustration />
          </div>
        </div>
      </section>

      {featured ? (
        <section
          aria-labelledby="featured-title"
          className="mx-auto w-full max-w-6xl px-6 pb-12"
        >
          <div className="scroll-reveal group rounded-lg border border-border-default bg-bg-surface p-6 transition-colors hover:border-border-strong lg:p-8">
            <p className="flex items-center gap-1.5 text-caption font-medium text-brand-primary">
              <Star aria-hidden="true" className="size-3.5" />
              Featured article
            </p>
            <div className="mt-5 grid gap-8 lg:grid-cols-[0.8fr_1.2fr]">
              <CoverPanel
                post={featured}
                className="min-h-48"
                sizes="(max-width: 1024px) 100vw, 420px"
              />
              <div className="space-y-4">
                <Badge variant="brand">{featured.category}</Badge>
                <h2 id="featured-title" className="text-h2 text-text-primary">
                  <Link
                    href={`/blog/${featured.slug}`}
                    className="rounded-sm hover:text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
                  >
                    {featured.title}
                  </Link>
                </h2>
                <p className="text-body-lg text-text-secondary">
                  {featured.description}
                </p>
                <p className="flex flex-wrap items-center gap-2 text-caption tabular-nums text-text-muted">
                  <span>The EduConnect team</span>
                  <span aria-hidden="true">·</span>
                  <time dateTime={featured.publishedAt}>
                    {formatPostDate(featured.publishedAt)}
                  </time>
                  <span aria-hidden="true">·</span>
                  <span>{featured.readingMinutes} min read</span>
                </p>
                <Link
                  href={`/blog/${featured.slug}`}
                  className={buttonClasses({ variant: "secondary" })}
                >
                  Read the article
                </Link>
              </div>
            </div>
          </div>
        </section>
      ) : null}

      <section
        aria-labelledby="all-posts-title"
        className="mx-auto w-full max-w-6xl space-y-6 px-6 pb-24"
      >
        <div className="scroll-reveal space-y-2">
          <Eyebrow>All articles</Eyebrow>
          <h2 id="all-posts-title" className="text-h3 text-text-primary">
            More product notes
          </h2>
        </div>
        <ul className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
          {rest.map((post) => (
            <li key={post.slug}>
              <article className="scroll-reveal group flex h-full flex-col overflow-hidden rounded-lg border border-border-default bg-bg-surface transition-all duration-300 hover:-translate-y-1 hover:border-border-strong">
                <CoverPanel
                  post={post}
                  className="h-36 rounded-b-none border-x-0 border-t-0"
                  sizes="(max-width: 768px) 100vw, 400px"
                />
                <div className="flex flex-1 flex-col gap-3 p-5">
                  <Badge variant="neutral" className="self-start">
                    {post.category}
                  </Badge>
                  <h3 className="text-h4 text-text-primary">
                    <Link
                      href={`/blog/${post.slug}`}
                      className="rounded-sm group-hover:text-brand-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
                    >
                      {post.title}
                    </Link>
                  </h3>
                  <p className="text-body text-text-secondary">
                    {post.description}
                  </p>
                  <p className="mt-auto flex flex-wrap items-center gap-2 pt-2 text-caption tabular-nums text-text-muted">
                    <time dateTime={post.publishedAt}>
                      {formatPostDate(post.publishedAt)}
                    </time>
                    <span aria-hidden="true">·</span>
                    <span>{post.readingMinutes} min read</span>
                  </p>
                </div>
              </article>
            </li>
          ))}
        </ul>
      </section>
    </div>
  );
}
