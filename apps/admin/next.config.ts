import type { NextConfig } from "next";

const isProd = process.env.NODE_ENV === "production";

/**
 * Origin the console is allowed to call for API/XHR traffic. The admin API runs
 * on a sibling host, so connect-src must name it explicitly beyond 'self'.
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
 * Content Security Policy for the restricted admin console. Script/style keep
 * 'unsafe-inline' because pages are statically pre-rendered (no stable nonce
 * without dynamic rendering); everything else is locked down, and the console
 * must never be framed. Development additionally allows eval/websockets for HMR.
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

const nextConfig: NextConfig = {
  reactStrictMode: true,
  transpilePackages: ["@educonnect/ui"],
  poweredByHeader: false,
  async headers() {
    return [{ source: "/:path*", headers: securityHeaders() }];
  },
};

export default nextConfig;
