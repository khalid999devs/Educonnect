import { describe, expect, it } from "vitest";

import type { Resource } from "@/lib/api/resources";
import {
  titleFromFileName,
  uploadsReducer,
  validateResourceFile,
  type UploadJob,
} from "./use-uploads";

function job(overrides: Partial<UploadJob> = {}): UploadJob {
  return {
    key: "upload-1",
    fileName: "notes.pdf",
    size: 1024,
    state: "hashing",
    percent: null,
    message: null,
    resource: null,
    ...overrides,
  };
}

const READY_RESOURCE = { id: "01JRES", version: 2 } as unknown as Resource;

describe("uploadsReducer", () => {
  it("adds a job in the initial hashing state", () => {
    const next = uploadsReducer([], {
      type: "added",
      key: "upload-1",
      fileName: "notes.pdf",
      size: 2048,
    });

    expect(next).toHaveLength(1);
    expect(next[0]).toMatchObject({ state: "hashing", percent: null });
  });

  it("tracks real transfer progress only while uploading", () => {
    const uploading = uploadsReducer(
      [job({ state: "uploading", percent: 0 })],
      {
        type: "progress",
        key: "upload-1",
        percent: 40,
      },
    );

    expect(uploading[0]?.percent).toBe(40);

    /* Progress events for a non-uploading job are ignored. */
    const confirming = uploadsReducer(
      [job({ state: "confirming", percent: null })],
      { type: "progress", key: "upload-1", percent: 90 },
    );

    expect(confirming[0]?.percent).toBeNull();
  });

  it("clears progress when leaving the uploading state", () => {
    const next = uploadsReducer([job({ state: "uploading", percent: 80 })], {
      type: "state",
      key: "upload-1",
      state: "confirming",
    });

    expect(next[0]).toMatchObject({ state: "confirming", percent: null });
  });

  it("records the resource returned by initiate/confirm", () => {
    const next = uploadsReducer([job()], {
      type: "resource",
      key: "upload-1",
      resource: READY_RESOURCE,
    });

    expect(next[0]?.resource).toBe(READY_RESOURCE);
  });

  it("never regresses a cancelled job to failed", () => {
    /* The aborted transport promise rejects after cancellation; the row
       must stay cancelled, not flip to a scary failure. */
    const next = uploadsReducer([job({ state: "cancelled" })], {
      type: "failed",
      key: "upload-1",
      message: "The upload was cancelled.",
    });

    expect(next[0]?.state).toBe("cancelled");
  });

  it("never regresses a completed job to failed", () => {
    const next = uploadsReducer([job({ state: "done" })], {
      type: "failed",
      key: "upload-1",
      message: "late error",
    });

    expect(next[0]?.state).toBe("done");
  });

  it("marks genuine failures with a message", () => {
    const next = uploadsReducer([job({ state: "uploading", percent: 30 })], {
      type: "failed",
      key: "upload-1",
      message: "The storage provider rejected the upload.",
    });

    expect(next[0]).toMatchObject({
      state: "failed",
      percent: null,
      message: "The storage provider rejected the upload.",
    });
  });

  it("dismisses a job from the list", () => {
    const next = uploadsReducer([job(), job({ key: "upload-2" })], {
      type: "dismissed",
      key: "upload-1",
    });

    expect(next).toHaveLength(1);
    expect(next[0]?.key).toBe("upload-2");
  });
});

describe("validateResourceFile", () => {
  it("rejects unsupported types", () => {
    const file = new File(["x"], "a.exe", { type: "application/x-msdownload" });

    expect(validateResourceFile(file)?.code).toBe("type");
  });

  it("rejects files over 25 MB", () => {
    const big = new File(["x"], "big.pdf", { type: "application/pdf" });

    Object.defineProperty(big, "size", { value: 26_214_401 });

    expect(validateResourceFile(big)?.code).toBe("size");
  });

  it("accepts a supported file within the limit", () => {
    const file = new File(["hello"], "notes.md", { type: "text/markdown" });

    expect(validateResourceFile(file)).toBeNull();
  });
});

describe("titleFromFileName", () => {
  it("strips the extension and trims", () => {
    expect(titleFromFileName("Operating Systems Notes.pdf")).toBe(
      "Operating Systems Notes",
    );
  });

  it("keeps a dotless name", () => {
    expect(titleFromFileName("README")).toBe("README");
  });

  it("caps the title at 160 characters", () => {
    expect(titleFromFileName(`${"a".repeat(200)}.pdf`)).toHaveLength(160);
  });
});
