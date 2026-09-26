import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { describe, expect, it, vi } from "vitest";

import { UploadDropzone } from "./upload-dropzone";

const REQUIRED_PROPS = {
  onFilesSelected: () => undefined,
  acceptDescription: "PDF, DOCX, or images",
  maxSizeDescription: "Up to 25 MB per file",
};

describe("UploadDropzone", () => {
  it("shows limits and the privacy note in the idle state", () => {
    render(
      <UploadDropzone
        {...REQUIRED_PROPS}
        status="idle"
        privacyNote="Only you can see uploads."
      />,
    );

    expect(screen.getByText(/PDF, DOCX, or images/)).toBeInTheDocument();
    expect(screen.getByText(/Up to 25 MB per file/)).toBeInTheDocument();
    expect(screen.getByText("Only you can see uploads.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Browse files" })).toBeEnabled();
  });

  it("passes selected files to the caller", async () => {
    const user = userEvent.setup();
    const onFilesSelected = vi.fn();
    const file = new File(["syllabus"], "syllabus.pdf", {
      type: "application/pdf",
    });

    const { container } = render(
      <UploadDropzone
        {...REQUIRED_PROPS}
        status="idle"
        onFilesSelected={onFilesSelected}
      />,
    );

    const input =
      container.querySelector<HTMLInputElement>('input[type="file"]');

    expect(input).not.toBeNull();

    await user.upload(input as HTMLInputElement, file);

    expect(onFilesSelected).toHaveBeenCalledWith([file]);
  });

  it("exposes real progress and a cancel action while uploading", async () => {
    const user = userEvent.setup();
    const onCancel = vi.fn();

    render(
      <UploadDropzone
        {...REQUIRED_PROPS}
        status="uploading"
        fileName="syllabus.pdf"
        progress={40}
        onCancel={onCancel}
      />,
    );

    expect(
      screen.getByRole("progressbar", { name: "Upload progress" }),
    ).toHaveAttribute("aria-valuenow", "40");
    expect(screen.getByText("syllabus.pdf")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Cancel upload" }));

    expect(onCancel).toHaveBeenCalledTimes(1);
  });

  it("offers retry with the error message in the error state", async () => {
    const user = userEvent.setup();
    const onRetry = vi.fn();

    render(
      <UploadDropzone
        {...REQUIRED_PROPS}
        status="error"
        fileName="syllabus.pdf"
        errorMessage="The connection dropped."
        onRetry={onRetry}
      />,
    );

    expect(screen.getByText("The connection dropped.")).toBeInTheDocument();

    await user.click(screen.getByRole("button", { name: "Retry upload" }));

    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it("confirms success and offers a reset", () => {
    render(
      <UploadDropzone
        {...REQUIRED_PROPS}
        status="success"
        fileName="syllabus.pdf"
        onReset={() => undefined}
      />,
    );

    expect(screen.getByText("syllabus.pdf uploaded")).toBeInTheDocument();
    expect(
      screen.getByRole("button", { name: "Upload another file" }),
    ).toBeInTheDocument();
  });
});
