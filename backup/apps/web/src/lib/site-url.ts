/**
 * Canonical site origin for SEO artifacts. Set SITE_URL in the deployment
 * environment; the localhost fallback keeps local builds functional and is
 * never a claim about production.
 */
export function siteUrl(): string {
  return process.env.SITE_URL ?? "http://localhost:3000";
}
