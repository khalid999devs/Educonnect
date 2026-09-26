"use client";

import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useCallback } from "react";

/**
 * The curated-catalog preference filter, shared by every section that lists
 * reviewed content (AI Tools, Templates).
 *
 * `preference=all|saved|dismissed|none` is implemented end to end on the
 * server for tools, prompts, workflows and templates. It is ALWAYS applied as
 * a query parameter: a page holds one cursor-paginated window of results, so
 * filtering `viewer_state` client-side would silently drop saved rows that sit
 * past the current page.
 *
 * The value is mirrored into the URL so a saved view is linkable. `all` is
 * encoded as the ABSENCE of the parameter, which keeps the canonical
 * `/ai-tools` and `/templates` URLs clean, and an unknown or tampered value
 * falls back to `all` rather than throwing.
 */
export const CATALOG_PREFERENCES = [
  "all",
  "saved",
  "dismissed",
  "none",
] as const;

export type CatalogPreference = (typeof CATALOG_PREFERENCES)[number];

export const PREFERENCE_PARAM = "preference";

export function isCatalogPreference(value: string): value is CatalogPreference {
  return (CATALOG_PREFERENCES as readonly string[]).includes(value);
}

/** Tamper-safe read: anything unrecognised means "no filter". */
export function readCatalogPreference(value: string | null): CatalogPreference {
  return value !== null && isCatalogPreference(value) ? value : "all";
}

export type CatalogPreferenceControl = {
  preference: CatalogPreference;
  setPreference: (next: CatalogPreference) => void;
  /** Convenience for the Saved chip, which is a two-state toggle. */
  savedOnly: boolean;
  toggleSaved: () => void;
};

export function useCatalogPreference(): CatalogPreferenceControl {
  const router = useRouter();
  const pathname = usePathname();
  const searchParams = useSearchParams();

  const preference = readCatalogPreference(searchParams.get(PREFERENCE_PARAM));

  const setPreference = useCallback(
    (next: CatalogPreference) => {
      const params = new URLSearchParams(searchParams.toString());

      if (next === "all") {
        params.delete(PREFERENCE_PARAM);
      } else {
        params.set(PREFERENCE_PARAM, next);
      }

      const query = params.toString();

      /* replace, not push: a filter click is not a navigation step. */
      router.replace(query === "" ? pathname : `${pathname}?${query}`, {
        scroll: false,
      });
    },
    [pathname, router, searchParams],
  );

  const savedOnly = preference === "saved";

  const toggleSaved = useCallback(
    () => setPreference(savedOnly ? "all" : "saved"),
    [savedOnly, setPreference],
  );

  return { preference, setPreference, savedOnly, toggleSaved };
}
