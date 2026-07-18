import type { Page } from "@playwright/test";

export const API = "http://localhost:8000";
const ORIGIN = "http://localhost:3101";

export function adminUser(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id: "01JADMIN0000000000000000AA",
    name: "Ada Admin",
    email: "admin@example.com",
    email_verified: true,
    primary_role: "admin",
    ...overrides,
  };
}

export function adminSession(overrides: Partial<Record<string, unknown>> = {}) {
  return {
    user: adminUser(),
    authorization: {
      roles: ["admin"],
      capabilities: ["admin.access"],
    },
    ...overrides,
  };
}

export function envelope(data: unknown) {
  return { data, meta: { request_id: "e2e-request" } };
}

export function validationError(field: string, message: string) {
  return {
    error: {
      code: "VALIDATION_FAILED",
      message: "The given data was invalid.",
      details: { fields: { [field]: [message] } },
      request_id: "e2e-request",
    },
  };
}

export async function mockCsrf(page: Page) {
  await page.route(`${API}/sanctum/csrf-cookie`, async (route) => {
    await route.fulfill({
      status: 204,
      headers: {
        "Set-Cookie": "XSRF-TOKEN=e2e-token; Path=/",
        "Access-Control-Allow-Origin": ORIGIN,
        "Access-Control-Allow-Credentials": "true",
      },
    });
  });
}

export async function mockJson(
  page: Page,
  url: string,
  body: unknown,
  status = 200,
) {
  await page.route(url, async (route) => {
    await route.fulfill({
      status,
      contentType: "application/json",
      headers: {
        "Access-Control-Allow-Origin": ORIGIN,
        "Access-Control-Allow-Credentials": "true",
        "Access-Control-Allow-Headers": "content-type,x-xsrf-token,accept",
        "Access-Control-Allow-Methods": "GET,POST,PUT,PATCH,DELETE,OPTIONS",
      },
      body: JSON.stringify(body),
    });
  });
}
