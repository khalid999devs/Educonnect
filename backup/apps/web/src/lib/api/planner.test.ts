import { describe, expect, it } from "vitest";

import { focusSessionSchema, taskSchema } from "./planner";

const TASK_FIXTURE = {
  id: "01JTASK0000000000000000000",
  version: 3,
  title: "Review AVL trees",
  description: null,
  course: {
    id: "01JCOURSE000000000000000000",
    version: 1,
    title: "Data Structures",
    code: "CS201",
    archive_status: "active",
  },
  due_at: "2026-07-17T09:30:00Z",
  status: "pending",
  completed_at: null,
  archive_status: "active",
  archived_at: null,
  created_at: "2026-07-10T09:00:00Z",
  updated_at: "2026-07-15T09:00:00Z",
};

const SESSION_FIXTURE = {
  id: "01JSESS0000000000000000000",
  version: 1,
  task: {
    id: "01JTASK0000000000000000000",
    version: 3,
    title: "Review AVL trees",
    status: "pending",
    archive_status: "active",
  },
  course: null,
  starts_at: "2026-07-17T04:00:00Z",
  ends_at: "2026-07-17T04:50:00Z",
  note: null,
  created_at: "2026-07-16T09:00:00Z",
  updated_at: "2026-07-16T09:00:00Z",
};

describe("planner schemas", () => {
  it("parses a task with a course reference", () => {
    const task = taskSchema.parse(TASK_FIXTURE);

    expect(task.course?.code).toBe("CS201");
    expect(task.status).toBe("pending");
  });

  it("parses a task with no course or due date", () => {
    const task = taskSchema.parse({
      ...TASK_FIXTURE,
      course: null,
      due_at: null,
    });

    expect(task.course).toBeNull();
    expect(task.due_at).toBeNull();
  });

  it("rejects an unknown task status", () => {
    expect(() =>
      taskSchema.parse({ ...TASK_FIXTURE, status: "archived" }),
    ).toThrow();
  });

  it("parses a focus session attached to a task", () => {
    const session = focusSessionSchema.parse(SESSION_FIXTURE);

    expect(session.task?.title).toBe("Review AVL trees");
    expect(session.course).toBeNull();
  });
});
