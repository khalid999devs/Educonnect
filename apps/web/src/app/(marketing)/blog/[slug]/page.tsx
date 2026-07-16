import { Badge, buttonClasses } from "@educonnect/ui";
import type { Metadata } from "next";
import Link from "next/link";
import { notFound } from "next/navigation";

import { BLOG_POSTS, formatPostDate, getBlogPost } from "@/content/blog";

export const dynamicParams = false;

export function generateStaticParams() {
  return BLOG_POSTS.map((post) => ({ slug: post.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const post = getBlogPost(slug);

  if (!post) {
    return {};
  }

  return {
    title: post.title,
    description: post.description,
    openGraph: {
      title: post.title,
      description: post.description,
      type: "article",
      publishedTime: post.publishedAt,
    },
  };
}

export default async function BlogPostPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const post = getBlogPost(slug);

  if (!post) {
    notFound();
  }

  return (
    <div className="mx-auto w-full max-w-3xl px-6 pb-24 pt-16">
      <article className="space-y-10">
        <header className="space-y-4">
          <Badge variant="brand">{post.category}</Badge>
          <p className="flex flex-wrap items-center gap-2 text-caption tabular-nums text-text-muted">
            <time dateTime={post.publishedAt}>
              {formatPostDate(post.publishedAt)}
            </time>
            <span aria-hidden="true">·</span>
            <span>{post.readingMinutes} min read</span>
            <span aria-hidden="true">·</span>
            <span>The EduConnect team</span>
          </p>
          <h1 className="text-h1 text-text-primary">{post.title}</h1>
          <p className="text-body-lg text-text-secondary">{post.description}</p>
        </header>

        {post.sections.map((section, index) => (
          <section key={section.heading ?? index} className="space-y-4">
            {section.heading ? (
              <h2 className="text-h3 text-text-primary">{section.heading}</h2>
            ) : null}
            {section.paragraphs.map((paragraph) => (
              <p key={paragraph} className="text-body-lg text-text-secondary">
                {paragraph}
              </p>
            ))}
            {section.list ? (
              <ul className="list-disc space-y-2 pl-5 text-body-lg text-text-secondary">
                {section.list.map((item) => (
                  <li key={item}>{item}</li>
                ))}
              </ul>
            ) : null}
          </section>
        ))}

        <footer className="flex flex-wrap gap-3 border-t border-border-subtle pt-8">
          <Link
            href="/blog"
            className={buttonClasses({ variant: "secondary" })}
          >
            All posts
          </Link>
          <Link href="/about" className={buttonClasses({ variant: "ghost" })}>
            About EduConnect
          </Link>
        </footer>
      </article>
    </div>
  );
}
