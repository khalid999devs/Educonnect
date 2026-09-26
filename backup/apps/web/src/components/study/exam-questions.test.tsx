import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it } from "vitest";

import type { ExamQuestion } from "@/lib/api/study";

import { ExamQuestions } from "./exam-questions";

const MULTIPLE_CHOICE: ExamQuestion = {
  prompt: "Which traversal visits every node at depth d before depth d+1?",
  options: ["Depth-first search", "Breadth-first search"],
  answer: "Breadth-first search",
  explanation: "The queue drains one level before the next is enqueued.",
};

const OPEN_RESPONSE: ExamQuestion = {
  prompt: "Explain why the queue ordering matters.",
  options: null,
  answer: "Because it enforces level order.",
  explanation: "Stated directly in the section on traversal order.",
};

describe("ExamQuestions", () => {
  it("hides the answer and the explanation until the student asks", async () => {
    const user = userEvent.setup();

    render(
      <ExamQuestions
        questions={[MULTIPLE_CHOICE]}
        sourceTitle="Lecture 4 notes"
      />,
    );

    expect(screen.queryByText(/The queue drains one level/)).toBeNull();

    const reveal = screen.getByRole("button", { name: /Reveal answer/ });
    expect(reveal).toHaveAttribute("aria-expanded", "false");

    await user.click(reveal);

    expect(screen.getByText(/The queue drains one level/)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Hide answer/ })).toHaveAttribute(
      "aria-expanded",
      "true",
    );
  });

  it("states where the answer came from and that it can be wrong", async () => {
    const user = userEvent.setup();

    render(
      <ExamQuestions
        questions={[MULTIPLE_CHOICE]}
        sourceTitle="Lecture 4 notes"
      />,
    );

    await user.click(screen.getByRole("button", { name: /Reveal answer/ }));

    expect(
      screen.getByText(/Written from your own material: Lecture 4 notes/),
    ).toBeInTheDocument();
    expect(screen.getByText(/it can be wrong/)).toBeInTheDocument();
  });

  it("renders an open-response question without inventing options", () => {
    render(
      <ExamQuestions
        questions={[OPEN_RESPONSE]}
        sourceTitle="Lecture 4 notes"
      />,
    );

    expect(screen.getByText("Open response")).toBeInTheDocument();
    expect(screen.queryByRole("radiogroup")).toBeNull();
  });

  it("records the student's choice without scoring it", async () => {
    const user = userEvent.setup();

    render(
      <ExamQuestions
        questions={[MULTIPLE_CHOICE]}
        sourceTitle="Lecture 4 notes"
      />,
    );

    const option = screen.getByRole("radio", { name: /Depth-first search/ });
    await user.click(option);

    expect(option).toHaveAttribute("aria-checked", "true");
    /* No score, no streak, no percentage anywhere on the surface. */
    expect(screen.queryByText(/correct/i)).toBeNull();
    expect(screen.queryByText(/%/)).toBeNull();
  });
});
