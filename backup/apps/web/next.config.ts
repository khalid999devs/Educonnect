import type { NextConfig } from "next";

const isProd = process.env.NODE_ENV === "production";

/**
 * Origin the SPA is allowed to call for API/XHR traffic. The API runs on a
 * sibling host, so connect-src must name it explicitly beyond 'self'.
 */
function apiOrigin(): string {
  try {
    return new URL(process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000")
      .origin;
  } catch {
    return "http://localhost:8000";
  }
}

/**
 * Content Security Policy. Script/style keep 'unsafe-inline' because the app is
 * statically pre-rendered (Next's inline bootstrap has no stable nonce without
 * opting every page into dynamic rendering); everything else is locked down.
 * Development additionally allows eval and websockets for Fast Refresh/HMR.
 */
function contentSecurityPolicy(): string {
  const scriptSrc = [
    "'self'",
    "'unsafe-inline'",
    ...(isProd ? [] : ["'unsafe-eval'"]),
  ];
  const connectSrc = [
    "'self'",
    apiOrigin(),
    ...(isProd ? [] : ["ws:", "http://localhost:*"]),
  ];

  return [
    "default-src 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "frame-ancestors 'none'",
    "form-action 'self'",
    `script-src ${scriptSrc.join(" ")}`,
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob:",
    "font-src 'self' data:",
    `connect-src ${connectSrc.join(" ")}`,
    "frame-src 'none'",
    "worker-src 'self' blob:",
    "manifest-src 'self'",
    ...(isProd ? ["upgrade-insecure-requests"] : []),
  ].join("; ");
}

function securityHeaders(): { key: string; value: string }[] {
  const headers = [
    { key: "Content-Security-Policy", value: contentSecurityPolicy() },
    { key: "X-Content-Type-Options", value: "nosniff" },
    { key: "X-Frame-Options", value: "DENY" },
    { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
    {
      key: "Permissions-Policy",
      value: "camera=(), microphone=(), geolocation=(), browsing-topics=()",
    },
    { key: "X-DNS-Prefetch-Control", value: "off" },
  ];

  if (isProd) {
    headers.push({
      key: "Strict-Transport-Security",
      value: "max-age=63072000; includeSubDomains; preload",
    });
  }

  return headers;
}

/**
 * Permanent redirects for the routes the information-architecture overhaul
 * retired. These are declared here rather than as `redirect()` stub pages so
 * that the retired route directories can be deleted outright: a config
 * redirect is answered as a real HTTP 308 before any React renders, which
 * browsers and crawlers cache, whereas a stub page would keep four route
 * segments alive purely to throw NEXT_REDIRECT on every request.
 *
 * `permanent: true` is correct here because none of these paths will ever be
 * reissued -- the sections were folded into their replacements, not moved
 * temporarily.
 */
function retiredRouteRedirects(): {
  source: string;
  destination: string;
  permanent: boolean;
}[] {
  return [
    // Smart Intake's standalone surface; the pipeline itself lives on inside
    // Second Brain, which took over its navigation slot.
    { source: "/intake", destination: "/second-brain", permanent: true },
    // Research is now a purpose facet of Second Brain rather than its own
    // section. Individual topic ids have no knowledge-item equivalent, so
    // deep links land on the filtered list rather than 404.
    {
      source: "/research",
      destination: "/second-brain?purpose=research",
      permanent: true,
    },
    {
      source: "/research/:id",
      destination: "/second-brain?purpose=research",
      permanent: true,
    },
    // Tools and prompts became the AI Tools section.
    { source: "/tools-prompts", destination: "/ai-tools", permanent: true },
    // Mentors became a tab of the Community hub; mentor detail pages moved
    // under that hub and keep a one-to-one mapping.
    {
      source: "/mentors",
      destination: "/community?tab=mentors",
      permanent: true,
    },
    {
      source: "/mentors/:id",
      destination: "/community/mentors/:id",
      permanent: true,
    },
  ];
}

const nextConfig: NextConfig = {
  reactStrictMode: true,
  transpilePackages: ["@educonnect/ui"],
  poweredByHeader: false,
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders() }];
  },
  async redirects() {
    return retiredRouteRedirects();
  },
};

export default nextConfig;
