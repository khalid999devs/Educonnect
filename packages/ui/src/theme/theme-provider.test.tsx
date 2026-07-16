import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";

import { ThemeProvider, useTheme } from "./theme-provider";
import { THEME_STORAGE_KEY } from "./theme-script";

function mockMatchMedia(prefersDark: boolean): void {
  vi.stubGlobal(
    "matchMedia",
    vi.fn().mockImplementation((query: string) => ({
      matches: query.includes("dark") ? prefersDark : false,
      media: query,
      addEventListener: vi.fn(),
      removeEventListener: vi.fn(),
    })),
  );
}

function Probe() {
  const { theme, resolvedTheme, setTheme } = useTheme();

  return (
    <div>
      <p data-testid="theme">{theme}</p>
      <p data-testid="resolved">{resolvedTheme}</p>
      <button type="button" onClick={() => setTheme("dark")}>
        go dark
      </button>
      <button type="button" onClick={() => setTheme("light")}>
        go light
      </button>
    </div>
  );
}

describe("ThemeProvider", () => {
  beforeEach(() => {
    window.localStorage.clear();
    document.documentElement.classList.remove("dark");
  });

  afterEach(() => {
    vi.unstubAllGlobals();
  });

  it("defaults to the system theme", () => {
    mockMatchMedia(false);

    render(
      <ThemeProvider>
        <Probe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId("theme")).toHaveTextContent("system");
    expect(screen.getByTestId("resolved")).toHaveTextContent("light");
    expect(document.documentElement).not.toHaveClass("dark");
  });

  it("resolves system to dark when the OS prefers dark", () => {
    mockMatchMedia(true);

    render(
      <ThemeProvider>
        <Probe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId("resolved")).toHaveTextContent("dark");
    expect(document.documentElement).toHaveClass("dark");
  });

  it("applies and persists an explicit choice", async () => {
    mockMatchMedia(false);
    const user = userEvent.setup();

    render(
      <ThemeProvider>
        <Probe />
      </ThemeProvider>,
    );

    await user.click(screen.getByRole("button", { name: "go dark" }));

    expect(document.documentElement).toHaveClass("dark");
    expect(document.documentElement.style.colorScheme).toBe("dark");
    expect(window.localStorage.getItem(THEME_STORAGE_KEY)).toBe("dark");

    await user.click(screen.getByRole("button", { name: "go light" }));

    expect(document.documentElement).not.toHaveClass("dark");
    expect(window.localStorage.getItem(THEME_STORAGE_KEY)).toBe("light");
  });

  it("honors a persisted choice on mount", () => {
    mockMatchMedia(false);
    window.localStorage.setItem(THEME_STORAGE_KEY, "dark");

    render(
      <ThemeProvider>
        <Probe />
      </ThemeProvider>,
    );

    expect(screen.getByTestId("theme")).toHaveTextContent("dark");
    expect(document.documentElement).toHaveClass("dark");
  });

  it("renders the pre-hydration init script before its children", () => {
    mockMatchMedia(false);

    const { container } = render(
      <ThemeProvider>
        <Probe />
      </ThemeProvider>,
    );

    const script = container.querySelector("script");

    expect(script).not.toBeNull();
    expect(script?.textContent).toContain(THEME_STORAGE_KEY);
  });
});
