import { render, screen } from "@testing-library/react";
import { describe, expect, it } from "vitest";

import { FormField } from "./form-field";
import { Input } from "./input";

describe("FormField", () => {
  it("associates the label with the rendered control", () => {
    render(
      <FormField label="Course name">
        {(control) => <Input {...control} />}
      </FormField>,
    );

    expect(screen.getByLabelText("Course name")).toBeInstanceOf(
      HTMLInputElement,
    );
  });

  it("wires hint and error into aria-describedby and marks the control invalid", () => {
    render(
      <FormField
        label="Email"
        hint="Use your university address."
        error="This email is already registered."
      >
        {(control) => <Input type="email" {...control} />}
      </FormField>,
    );

    const input = screen.getByLabelText("Email");
    const describedBy = input.getAttribute("aria-describedby") ?? "";

    expect(input).toHaveAttribute("aria-invalid", "true");
    expect(describedBy.split(" ")).toHaveLength(2);
    expect(screen.getByText("Use your university address.")).toHaveAttribute(
      "id",
      expect.stringContaining("-hint"),
    );
    expect(
      screen.getByText("This email is already registered."),
    ).toHaveAttribute("id", expect.stringContaining("-error"));
  });

  it("keeps valid fields free of error wiring", () => {
    render(
      <FormField label="Term">{(control) => <Input {...control} />}</FormField>,
    );

    const input = screen.getByLabelText("Term");

    expect(input).not.toHaveAttribute("aria-invalid");
    expect(input).not.toHaveAttribute("aria-describedby");
  });
});
