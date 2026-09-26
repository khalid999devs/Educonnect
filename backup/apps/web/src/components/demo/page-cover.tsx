/**
 * The demo cover band was promoted to `components/shared/page-cover.tsx` so
 * the authenticated sections and the marketing demo render the identical
 * component. This re-export keeps every existing demo call site working.
 */
export { PageCover, type PageCoverProps } from "@/components/shared/page-cover";
