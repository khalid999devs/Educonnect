import { fireEvent, render, screen, within } from "@testing-library/react";
import { describe, expect, it, vi } from "vitest";

import { UNFILED_COURSE_ID, type ResourceDirectory } from "@/lib/api/resources";

import {
  ARCHIVED_DIRECTORY_REASON,
  DirectoryCard,
  directoryKey,
  directoryTitle,
  type DirectoryCourse,
} from "./directory-card";
import { DirectoryBrowser } from "./directory-browser";

function courseDirectory(
  overrides: Partial<DirectoryCourse> = {},
  resourceCount = 3,
): ResourceDirectory {
  return {
    kind: "course",
    course: {
      id: "01hzzzzzzzzzzzzzzzzzzzzzzz",
      version: 1,
      title: "Algorithms",
      code: "CS-201",
      archive_status: "active",
      ...overrides,
    },
    resource_count: resourceCount,
  };
}

const unfiledDirectory: ResourceDirectory = {
  kind: "unfiled",
  course: null,
  resource_count: 2,
};

function fileDrop(files: File[]) {
  return {
    dataTransfer: {
      files,
      items: files.map((file) => ({ kind: "file", type: file.type, file })),
      types: ["Files"],
    },
  };
}

describe("directoryKey", () => {
  it("uses the course public id for a course directory", () => {
    expect(directoryKey(courseDirectory())).toBe("01hzzzzzzzzzzzzzzzzzzzzzzz");
  });

  it("uses the course_id=none sentinel for the unfiled bucket", () => {
    expect(directoryKey(unfiledDirectory)).toBe(UNFILED_COURSE_ID);
    expect(UNFILED_COURSE_ID).toBe("none");
  });

  it("names the unfiled bucket rather than showing a blank title", () => {
    expect(directoryTitle(unfiledDirectory)).toBe("Unfiled");
  });
});

describe("DirectoryCard", () => {
  it("shows the resource count with tabular figures", () => {
    render(
      <DirectoryCard
        directory={courseDirectory({}, 12)}
        selected={false}
        onSelect={vi.fn()}
      />,
    );

    const count = screen.getByText("12 items");

    expect(count.className).toContain("tabular-nums");
  });

  it("marks the open directory with aria-pressed", () => {
    render(
      <DirectoryCard
        directory={courseDirectory()}
        selected
        onSelect={vi.fn()}
      />,
    );

    expect(screen.getByRole("button")).toHaveAttribute("aria-pressed", "true");
  });

  it("accepts a file drop into an active directory", () => {
    const onDropFiles = vi.fn();

    render(
      <DirectoryCard
        directory={courseDirectory()}
        selected={false}
        onSelect={vi.fn()}
        onDropFiles={onDropFiles}
      />,
    );

    const file = new File(["x"], "notes.pdf", { type: "application/pdf" });

    fireEvent.drop(screen.getByRole("button"), fileDrop([file]));

    expect(onDropFiles).toHaveBeenCalledWith([file]);
  });

  it("refuses a drop into an archived directory and says why", () => {
    const onDropFiles = vi.fn();

    render(
      <DirectoryCard
        directory={courseDirectory({ archive_status: "archived" })}
        selected={false}
        onSelect={vi.fn()}
        onDropFiles={onDropFiles}
      />,
    );

    const card = screen.getByRole("button");
    const file = new File(["x"], "notes.pdf", { type: "application/pdf" });

    fireEvent.dragOver(card, fileDrop([file]));

    expect(screen.getByText(ARCHIVED_DIRECTORY_REASON)).toBeInTheDocument();

    fireEvent.drop(card, fileDrop([file]));

    expect(onDropFiles).not.toHaveBeenCalled();
  });
});

describe("DirectoryBrowser", () => {
  const baseProps = {
    loading: false,
    error: false,
    onRetry: vi.fn(),
    selectedKey: UNFILED_COURSE_ID,
    onSelect: vi.fn(),
    onDropFiles: vi.fn(),
  };

  it("renders archived courses in their own section so their material never vanishes", () => {
    render(
      <DirectoryBrowser
        {...baseProps}
        directories={[
          courseDirectory(),
          courseDirectory({
            id: "01hyyyyyyyyyyyyyyyyyyyyyyy",
            title: "Discrete Maths",
            code: "MA-140",
            archive_status: "archived",
          }),
          unfiledDirectory,
        ]}
      />,
    );

    const archivedSection = screen.getByRole("region", {
      name: "Archived courses",
    });

    expect(
      within(archivedSection).getByText(/Discrete Maths/),
    ).toBeInTheDocument();
    expect(
      within(archivedSection).queryByText(/Algorithms/),
    ).not.toBeInTheDocument();
  });

  it("renders a retry affordance when the directories fail to load", () => {
    const onRetry = vi.fn();

    render(
      <DirectoryBrowser
        {...baseProps}
        directories={[]}
        error
        onRetry={onRetry}
      />,
    );

    fireEvent.click(screen.getByRole("button", { name: /try again|retry/i }));

    expect(onRetry).toHaveBeenCalled();
  });

  it("renders an honest empty state when there are no directories at all", () => {
    render(<DirectoryBrowser {...baseProps} directories={[]} />);

    expect(screen.getByText("No directories yet")).toBeInTheDocument();
  });
});
