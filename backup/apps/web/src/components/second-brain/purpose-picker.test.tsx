import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { PurposePicker } from "./purpose-picker";

describe("PurposePicker", () => {
  it("exposes all four purposes as one radiogroup", () => {
    render(
      <PurposePicker
        label="What is this for"
        value={null}
        onChange={vi.fn()}
      />,
    );

    expect(screen.getByRole("radiogroup")).toHaveAccessibleName(
      "What is this for",
    );
    expect(screen.getAllByRole("radio")).toHaveLength(4);
  });

  it("marks the backend's pre-selection as a suggestion, not a decision", () => {
    render(
      <PurposePicker
        label="What is this for"
        value="exam"
        suggested="exam"
        onChange={vi.fn()}
      />,
    );

    expect(screen.getByText("Suggested")).toBeInTheDocument();
    expect(
      screen.getByRole("radio", { name: /Exam preparation/ }),
    ).toBeChecked();
  });

  it("corrects a wrong pre-selection in ONE click", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();

    render(
      <PurposePicker
        label="What is this for"
        value="resource"
        suggested="resource"
        onChange={onChange}
      />,
    );

    await user.click(screen.getByRole("radio", { name: /Research/ }));

    expect(onChange).toHaveBeenCalledTimes(1);
    expect(onChange).toHaveBeenCalledWith("research");
  });

  it("moves between purposes with the arrow keys", async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();

    render(
      <PurposePicker
        label="What is this for"
        value="resource"
        onChange={onChange}
      />,
    );

    await user.tab();
    await user.keyboard("{ArrowRight}");

    expect(onChange).toHaveBeenCalledWith("study");
  });
});
