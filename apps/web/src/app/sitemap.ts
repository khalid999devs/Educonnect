import type { MetadataRoute } from "next";

import { BLOG_POSTS } from "@/content/blog";
import { siteUrl } from "@/lib/site-url";

export default function sitemap(): MetadataRoute.Sitemap {
  const base = siteUrl();

  const marketingPages: MetadataRoute.Sitemap = [
    { url: `${base}/`, priority: 1 },
    { url: `${base}/demo`, priority: 0.9 },
    { url: `${base}/about`, priority: 0.7 },
    { url: `${base}/blog`, priority: 0.6 },
    { url: `${base}/contact`, priority: 0.5 },
  ];

  const posts: MetadataRoute.Sitemap = BLOG_POSTS.map((post) => ({
    url: `${base}/blog/${post.slug}`,
    lastModified: post.publishedAt,
    priority: 0.5,
  }));

  return [...marketingPages, ...posts];
}
