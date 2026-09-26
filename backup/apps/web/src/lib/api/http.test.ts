import { describe, expect, it } from "vitest";

import { ApiError, envelopeData } from "./http";

describe("ApiError", () => {
  it("maps the backend error envelope", () => {
    const error = ApiError.fromPayload(422, {
      error: {
        code: "VALIDATION_FAILED",
        message: "The given data was invalid.",
        details: {
          fields: {
            email: ["The email has already been taken."],
            "data.institution_name": ["This field is required."],
          },
        },
        request_id: "req-123",
      },
    });

    expect(error.status).toBe(422);
    expect(error.code).toBe("VALIDATION_FAILED");
    expect(error.requestId).toBe("req-123");
    expect(error.fieldError("email")).toBe("The email has already been taken.");
    expect(error.fieldError("institution_name")).toBe(
      "This field is required.",
    );
  });

  it("falls back safely for non-envelope payloads", () => {
    const error = ApiError.fromPayload(500, "<html>oops</html>");

    expect(error.code).toBe("UNKNOWN");
    expect(error.fieldError("anything")).toBeUndefined();
  });
});

describe("envelopeData", () => {
  it("unwraps the data member", () => {
    expect(envelopeData({ data: { user: { id: "1" } }, meta: {} })).toEqual({
      user: { id: "1" },
    });
  });

  it("passes through non-envelope payloads", () => {
    expect(envelopeData(null)).toBeNull();
  });
});
