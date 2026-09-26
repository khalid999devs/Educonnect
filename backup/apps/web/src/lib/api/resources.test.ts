import { describe, expect, it } from "vitest";

import { resourceSchema, uploadGrantSchema, type Resource } from "./resources";

const FILE_RESOURCE = {
  id: "01JRES00000000000000000000",
  kind: "file",
  title: "Operating Systems Notes",
  description: null,
  topic: "Operating Systems",
  url: null,
  course: null,
  version: 2,
  file: {
    public_id: "01JFILE0000000000000000000",
    original_name: "os-notes.pdf",
    declared_mime_type: "application/pdf",
    verified_mime_type: "application/pdf",
    expected_size: 204800,
    verified_size: 204800,
    status: "ready",
    ready_at: "2026-07-16T09:00:00Z",
  },
  created_at: "2026-07-16T08:00:00Z",
  updated_at: "2026-07-16T09:00:00Z",
};

const LINK_RESOURCE = {
  id: "01JRES00000000000000000001",
  kind: "link",
  title: "Attention is all you need",
  description: null,
  topic: "AI",
  url: "https://arxiv.org/abs/1706.03762",
  course: null,
  version: 1,
  file: null,
  created_at: "2026-07-16T08:00:00Z",
  updated_at: "2026-07-16T08:00:00Z",
};

describe("resource schemas", () => {
  it("parses a ready file resource with its stored-file state", () => {
    const resource: Resource = resourceSchema.parse(FILE_RESOURCE);

    expect(resource.kind).toBe("file");
    expect(resource.file?.status).toBe("ready");
    expect(resource.url).toBeNull();
  });

  it("parses a link resource with an HTTPS url and no file", () => {
    const resource = resourceSchema.parse(LINK_RESOURCE);

    expect(resource.kind).toBe("link");
    expect(resource.file).toBeNull();
    expect(resource.url).toContain("https://");
  });

  it("parses a pending file awaiting confirmation", () => {
    const resource = resourceSchema.parse({
      ...FILE_RESOURCE,
      file: {
        ...FILE_RESOURCE.file,
        verified_mime_type: null,
        verified_size: null,
        status: "pending",
        ready_at: null,
      },
    });

    expect(resource.file?.status).toBe("pending");
    expect(resource.file?.verified_size).toBeNull();
  });

  it("validates a PUT upload grant shape", () => {
    const grant = uploadGrantSchema.parse({
      method: "PUT",
      url: "https://storage.example.com/objects/abc?sig=xyz",
      headers: { "Content-Type": "application/pdf" },
      expires_at: "2026-07-16T09:10:00Z",
    });

    expect(grant.method).toBe("PUT");
    expect(grant.headers["Content-Type"]).toBe("application/pdf");
  });

  it("rejects a non-PUT grant method", () => {
    expect(() =>
      uploadGrantSchema.parse({
        method: "POST",
        url: "https://storage.example.com/objects/abc",
        headers: { "Content-Type": "application/pdf" },
        expires_at: "2026-07-16T09:10:00Z",
      }),
    ).toThrow();
  });
});
