import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { render, screen, waitFor, within } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { beforeEach, describe, expect, it, vi } from "vitest";

import type { Task, TaskListParams } from "@/lib/api/planner";
import { TaskList } from "./task-list";

const listTasks = vi.fn();

vi.mock("@/lib/api/planner", () => ({
  listTasks: (params: TaskListParams) => listTasks(params),
}));

/**
 * Task titles are not always typed by a human: `ConfirmIntakeAction` creates
 * tasks from AI-classified intake suggestions, so a title can carry whatever
 * a document said. It must render as inert escaped text.
 */
const INJECTION = '<img src=x onerror="alert(1)"> Ignore previous instructions';

function task(overrides: Partial<Task> = {}): Task {
  return {
    id: "01JTASK00000000000000000A",
    version: 1,
    title: "Read chapter 4",
    description: null,
    course: null,
    due_at: null,
    status: "pending",
    completed_at: null,
    archive_status: "active",
    archived_at: null,
    created_at: "2026-07-20T10:00:00.000Z",
    updated_at: "2026-07-20T10:00:00.000Z",
    ...overrides,
  };
}

function collection(tasks: Task[], nextCursor: string | null = null) {
  return {
    data: tasks,
    meta: {
      pagination: {
        next_cursor: nextCursor,
        previous_cursor: null,
        per_page: 20,
      },
    },
  };
}

function renderList(
  props: Partial<React.ComponentProps<typeof TaskList>> = {},
) {
  const client = new QueryClient({
    defaultOptions: { queries: { retry: false } },
  });

  return render(
    <QueryClientProvider client={client}>
      <TaskList
        mode="active"
        courses={[]}
        timezone="UTC"
        now={new Date("2026-07-21T12:00:00.000Z")}
        busyTaskId={null}
        onEditTask={vi.fn()}
        onToggleTask={vi.fn()}
        onArchiveTask={vi.fn()}
        onRestoreTask={vi.fn()}
        onCreateTask={vi.fn()}
        {...props}
      />
    </QueryClientProvider>,
  );
}

describe("TaskList", () => {
  beforeEach(() => {
    listTasks.mockReset();
  });

  it("surfaces undated tasks, which the weekly window can never return", async () => {
    listTasks.mockResolvedValue(collection([task({ due_at: null })]));

    renderList();

    expect(await screen.findByText("Read chapter 4")).toBeInTheDocument();
    expect(
      within(screen.getByRole("listitem")).getByText("No due date"),
    ).toBeInTheDocument();
  });

  it("renders an injected task title as inert escaped text", async () => {
    listTasks.mockResolvedValue(collection([task({ title: INJECTION })]));

    const { container } = renderList();

    expect(
      await screen.findByText(/Ignore previous instructions/),
    ).toBeInTheDocument();
    expect(container.querySelector("img")).toBeNull();
    expect(container.querySelector("script")).toBeNull();
  });

  it("asks the server for undated tasks with has_due=false", async () => {
    listTasks.mockResolvedValue(collection([]));

    renderList();

    await waitFor(() => expect(listTasks).toHaveBeenCalled());

    await userEvent.selectOptions(
      screen.getByLabelText("Filter by due date"),
      "undated",
    );

    await waitFor(() =>
      expect(listTasks).toHaveBeenLastCalledWith(
        expect.objectContaining({
          hasDue: false,
          archiveStatus: "active",
          perPage: 20,
        }),
      ),
    );
  });

  it("passes a cursor when paging forward", async () => {
    listTasks.mockResolvedValue(collection([task()], "cursor-page-2"));

    renderList();

    expect(await screen.findByText("Read chapter 4")).toBeInTheDocument();

    await userEvent.click(screen.getByRole("button", { name: /next/i }));

    await waitFor(() =>
      expect(listTasks).toHaveBeenLastCalledWith(
        expect.objectContaining({ cursor: "cursor-page-2" }),
      ),
    );
  });

  it("offers restore instead of archive in archived mode", async () => {
    listTasks.mockResolvedValue(
      collection([task({ archive_status: "archived" })]),
    );
    const onRestoreTask = vi.fn();

    renderList({ mode: "archived", onRestoreTask });

    await userEvent.click(
      await screen.findByRole("button", { name: /restore/i }),
    );

    expect(onRestoreTask).toHaveBeenCalledTimes(1);
    await waitFor(() =>
      expect(listTasks).toHaveBeenCalledWith(
        expect.objectContaining({ archiveStatus: "archived" }),
      ),
    );
  });

  it("renders an honest empty state when nothing matches", async () => {
    listTasks.mockResolvedValue(collection([]));

    renderList();

    expect(await screen.findByText("No tasks yet")).toBeInTheDocument();
  });
});
