import { describe, expect, it } from "vitest";

import { ApiError, envelopeData } from "./http";

describe("ApiError", () => {
  it("maps the backend validation error envelope, including data.-prefixed keys", () => {
    const error = ApiError.fromPayload(422, {
      error: {
        code: "VALIDATION_FAILED",
        message: "The given data was invalid.",
        details: {
          fields: {
            email: ["The provided credentials are incorrect."],
          },
        },
        request_id: "req-123",
      },
    });

    expect(error.status).toBe(422);
    expect(error.code).toBe("VALIDATION_FAILED");
    expect(error.requestId).toBe("req-123");
    expect(error.fieldError("email")).toBe(
      "The provided credentials are incorrect.",
    );
  });

  it("falls back to a generic error when the payload is not an envelope", () => {
    const error = ApiError.fromPayload(500, null);

    expect(error.code).toBe("UNKNOWN");
    expect(error.fieldError("email")).toBeUndefined();
  });
});

describe("envelopeData", () => {
  it("unwraps the success envelope's data member", () => {
    expect(envelopeData({ data: { user: { id: "1" } } })).toEqual({
      user: { id: "1" },
    });
  });

  it("returns the payload unchanged when there is no data member", () => {
    expect(envelopeData({ user: { id: "1" } })).toEqual({ user: { id: "1" } });
  });
});
