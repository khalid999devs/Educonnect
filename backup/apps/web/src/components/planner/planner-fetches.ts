/**
 * Cursor-draining fetches the planner's pickers need.
 *
 * The query-key shims that used to live here are gone: `plannerKeys.tasks`,
 * `plannerKeys.taskPicker` and the parameterised `courseKeys.list` now exist in
 * `@/lib/query-keys`, and `fetchAllActiveCourses` lives in `@/lib/api/courses`
 * where every other course picker reads it from.
 */

import { listTasks, type Task, type TaskListParams } from "@/lib/api/planner";

const TASK_PICKER_PAGE_SIZE = 50;

/**
 * The focus-session task picker: recently updated ACTIVE tasks, filtered
 * server-side rather than in the component, and bounded to three cursor pages.
 * A picker is a dropdown, not a browsing surface - the Tasks tab is where the
 * full, filterable, fully paged list lives.
 */
const MAX_TASK_PICKER_PAGES = 3;

export async function fetchTaskPickerOptions(): Promise<Task[]> {
  const collected: Task[] = [];
  let cursor: string | undefined;

  for (let page = 0; page < MAX_TASK_PICKER_PAGES; page += 1) {
    const params: TaskListParams = {
      status: "all",
      archiveStatus: "active",
      sort: "-updated_at",
      perPage: TASK_PICKER_PAGE_SIZE,
      cursor,
    };
    const response = await listTasks(params);

    collected.push(...response.data);

    const next = response.meta.pagination.next_cursor;

    if (next === null) {
      break;
    }

    cursor = next;
  }

  return collected;
}
