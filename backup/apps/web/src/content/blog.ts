/**
 * Curated blog content, stored in the repository and statically generated.
 * A CMS arrives only if publishing volume ever justifies one (ADR-0018).
 */

export type BlogSection = {
  heading?: string;
  paragraphs: string[];
  list?: string[];
};

export type BlogCategory = "Product" | "Principles" | "How it works";

export type BlogPost = {
  slug: string;
  title: string;
  description: string;
  category: BlogCategory;
  /** CC0 cover photo under public/marketing (see IMAGE_CREDITS.md). */
  cover: string;
  /** ISO date, fixed at authoring time. */
  publishedAt: string;
  readingMinutes: number;
  sections: BlogSection[];
};

export const BLOG_POSTS: BlogPost[] = [
  {
    slug: "why-educonnect-exists",
    title: "Why EduConnect exists",
    description:
      "Students don't lack tools. They lack a path from 'I need to do this' to 'it's done and stored where I'll find it'. That gap is the product.",
    category: "Product",
    cover: "/marketing/notebook-pens.jpg",
    publishedAt: "2026-07-10",
    readingMinutes: 4,
    sections: [
      {
        paragraphs: [
          "Ask a university student where their coursework lives and you'll get a tour: files in three cloud drives, deadlines in a screenshot of the syllabus, notes split between two apps and a group chat, half-finished AI conversations, and a calendar that stopped being true in week three.",
          "None of this happens because students are careless. It happens because every tool solves one slice of the problem and none of them owns the path between slices. The syllabus PDF knows the deadlines; the calendar doesn't. The AI chat produced a good outline; it's gone now. The productivity app tracked a streak; it never knew what the tasks were for.",
        ],
      },
      {
        heading: "The gap is guidance, not features",
        paragraphs: [
          "Most students already know that flashcard apps, citation managers, and AI assistants exist. The questions that actually block them are situational: which of these fits the assignment in front of me? What do I ask it? Is this a responsible way to use it for graded work? And once I have an output, where does it go so future-me finds it?",
          "EduConnect is built around those questions. You start from a goal, not a tool list. The guide recommends specific tools with the reasoning attached, hands you an editable prompt and a step-by-step workflow, and (this is the part the scattered stack never does) ends every path with a destination: the course, task, or research topic where the result belongs.",
        ],
      },
      {
        heading: "One loop, not another app to maintain",
        paragraphs: [
          "The product is deliberately shaped as a loop rather than a pile of modules: capture material with Quick Intake, review what was extracted, confirm it into tasks and resources, work with guidance, and return to a dashboard that shows the real state of your week.",
          "Every step in that loop is review-first. Extraction produces suggestions, not silent changes. Progress is computed from records you actually created. If the week was empty, the dashboard says so plainly. Motivation built on false numbers isn't motivation, it's noise.",
        ],
      },
      {
        paragraphs: [
          "The Live Demo on this site walks exactly that loop on sample data, in your browser, with nothing uploaded or stored. If it resonates, the blog is where we'll keep explaining decisions as the product opens up.",
        ],
      },
    ],
  },
  {
    slug: "truthful-progress",
    title: "Truthful progress, or: why we refuse to fake your numbers",
    description:
      "Streaks, badges, and inflated percentages are easy engagement. We think they're corrosive in an academic tool, so EduConnect's progress model has one rule: real records only.",
    category: "Principles",
    cover: "/marketing/minimal-desk.jpg",
    publishedAt: "2026-07-13",
    readingMinutes: 3,
    sections: [
      {
        paragraphs: [
          "Productivity software has a dirty habit: when real progress is missing, it manufactures the feeling of progress instead. Streaks that punish a sick day. Completion rings that count opening the app. 'You're 80% there!' bars whose denominator no one can explain.",
          "In an academic tool this isn't harmless gamification. It teaches you to distrust your own dashboard exactly when you need it most: exam week, thesis crunch, the days when knowing the true state of your work matters.",
        ],
      },
      {
        heading: "The rule",
        paragraphs: [
          "EduConnect's progress model has a single rule: every number on screen must be derivable from records you created, inside a timeframe we show you explicitly.",
        ],
        list: [
          "Completed 3 of 7 tasks due this week, because those tasks exist and three have completion timestamps.",
          "95 focus minutes, because focus sessions with those bounds are stored.",
          '"No planner activity recorded this week yet." That line appears because an empty week is information, not a failure to be masked.',
          "A next action that names a real task or a real item awaiting review, never a motivational placeholder.",
        ],
      },
      {
        heading: "It extends to marketing",
        paragraphs: [
          "The same policy applies to this website. You won't find invented user counts, fabricated testimonials, or university logos we have no relationship with. The Live Demo is labeled as sample data everywhere it appears, and the about page states plainly what is built and what isn't yet.",
          "If a product's own marketing lies to you with numbers, why would its dashboard be different? We'd rather earn slower trust than fast doubt.",
        ],
      },
    ],
  },
  {
    slug: "how-quick-intake-works",
    title: "From syllabus to plan: how Quick Intake works",
    description:
      "Upload a file or paste a link, get suggested tasks and organized resources, with you reviewing every suggestion before anything is created.",
    category: "How it works",
    cover: "/marketing/shelf-books.jpg",
    publishedAt: "2026-07-15",
    readingMinutes: 5,
    sections: [
      {
        paragraphs: [
          "The most valuable documents in a semester (syllabi, assignment briefs, lab schedules) are also the ones most likely to rot in a downloads folder. Quick Intake exists to move them from 'file I have somewhere' to 'deadlines on my planner and a resource on the right course' in one reviewed pass.",
        ],
      },
      {
        heading: "The pipeline, honestly described",
        paragraphs: [
          "When you hand EduConnect a file or a link, a background pipeline takes over. Each stage is observable: the item's status is always visible, and you can cancel or retry.",
        ],
        list: [
          "Acquire: files go straight to private storage; links are fetched with strict safety checks (HTTPS-only, every redirect revalidated, size- and type-bounded).",
          "Extract: the readable text is pulled out of the document. No content, no problem: the item simply reports that extraction found nothing usable.",
          "Suggest: classification proposes a title, a type, a course match from your own courses, tags, and any dates it can defend. Each suggestion carries its reason and a confidence level.",
          "Review: nothing becomes a task or resource yet. You see every suggestion, edit or reject freely, and pick destinations.",
          "Confirm: only your confirmation creates records (tasks on the planner, the source as a resource on the course) atomically and without duplicates on retry.",
        ],
      },
      {
        heading: "Why review-first is non-negotiable",
        paragraphs: [
          "Automatic extraction is probabilistic; your planner is not. A tool that silently writes guessed deadlines into your week trades a small convenience for a large risk: one wrong date quietly planted in week two surfaces as a missed submission in week six.",
          "So the boundary is structural: the extraction pipeline can only ever produce suggestions. The write path into your workspace goes through you. That's slightly more friction on the happy path and dramatically less damage on the unhappy one: the correct trade for academic data.",
          "You can walk this exact flow (upload, staged processing, suggestions, review, confirm) in the Live Demo with a sample syllabus, right in your browser.",
        ],
      },
    ],
  },
];

export function getBlogPost(slug: string): BlogPost | undefined {
  return BLOG_POSTS.find((post) => post.slug === slug);
}

export function formatPostDate(isoDate: string): string {
  return new Date(`${isoDate}T00:00:00Z`).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
    timeZone: "UTC",
  });
}
