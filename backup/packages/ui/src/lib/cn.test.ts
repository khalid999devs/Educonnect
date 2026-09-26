import { describe, expect, it } from "vitest";

import { cn } from "./cn";

describe("cn", () => {
  it("merges conditional class values", () => {
    expect(cn("a", false && "b", undefined, "c")).toBe("a c");
  });

  it("resolves conflicts within the semantic type scale", () => {
    expect(cn("text-body", "text-h4")).toBe("text-h4");
  });

  it("keeps a type-scale class alongside a semantic text color", () => {
    expect(cn("text-h4", "text-text-primary")).toBe(
      "text-h4 text-text-primary",
    );
  });

  it("lets a later text color override an earlier one", () => {
    expect(cn("text-text-secondary", "text-status-error")).toBe(
      "text-status-error",
    );
  });

  it("lets callers override positioning utilities", () => {
    expect(cn("fixed bottom-6 right-6", "static")).toBe(
      "bottom-6 right-6 static",
    );
  });
});
