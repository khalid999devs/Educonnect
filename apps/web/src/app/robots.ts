import type { MetadataRoute } from "next";

import { siteUrl } from "@/lib/site-url";

export default function robots(): MetadataRoute.Robots {
  return {
    rules: [
      {
        userAgent: "*",
        allow: "/",
        /* Product shell and internal component gallery are not marketing
           surface; the admin console is a separate noindex application. */
        disallow: ["/dashboard", "/design-system"],
      },
    ],
    sitemap: `${siteUrl()}/sitemap.xml`,
  };
}
