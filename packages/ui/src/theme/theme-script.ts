export const THEME_STORAGE_KEY = "educonnect-theme";

export type Theme = "light" | "dark" | "system";

export type ResolvedTheme = "light" | "dark";

/**
 * Inline pre-hydration script that applies the persisted theme (or the system
 * preference) before first paint so no route ever flashes the wrong theme.
 * ThemeProvider renders it as the first element inside <body>.
 */
export const themeInitScript = [
  "(function(){try{",
  `var s=localStorage.getItem("${THEME_STORAGE_KEY}");`,
  'var m=window.matchMedia("(prefers-color-scheme: dark)").matches;',
  'var d=s==="dark"||(s!=="light"&&m);',
  "var r=document.documentElement;",
  'r.classList.toggle("dark",d);',
  'r.style.colorScheme=d?"dark":"light";',
  "}catch(e){}})();",
].join("");

export function isTheme(value: unknown): value is Theme {
  return value === "light" || value === "dark" || value === "system";
}
