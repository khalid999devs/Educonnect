import { apiFetch, envelopeData } from "./http";
import { userSchema, type User } from "./schemas";

function parseUser(payload: unknown): User {
  const data = envelopeData(payload);

  return userSchema.parse(
    typeof data === "object" && data !== null && "user" in data
      ? (data as { user: unknown }).user
      : data,
  );
}

export async function register(input: {
  name: string;
  email: string;
  password: string;
}): Promise<User> {
  return parseUser(
    await apiFetch("/api/v1/auth/register", { method: "POST", body: input }),
  );
}

export async function login(input: {
  email: string;
  password: string;
}): Promise<User> {
  return parseUser(
    await apiFetch("/api/v1/auth/login", { method: "POST", body: input }),
  );
}

export async function logout(): Promise<void> {
  await apiFetch("/api/v1/auth/logout", { method: "POST" });
}

export async function forgotPassword(email: string): Promise<void> {
  await apiFetch("/api/v1/auth/forgot-password", {
    method: "POST",
    body: { email },
  });
}

export async function resetPassword(input: {
  token: string;
  email: string;
  password: string;
}): Promise<void> {
  await apiFetch("/api/v1/auth/reset-password", {
    method: "POST",
    body: input,
  });
}

export async function resendVerificationEmail(): Promise<void> {
  await apiFetch("/api/v1/auth/email/verification-notification", {
    method: "POST",
  });
}

/**
 * Performs the authenticated fetch against the signed verification URL the
 * email carries (the endpoint rejects plain navigation by design).
 */
export async function verifyEmailWithSignedUrl(
  signedUrl: string,
): Promise<User> {
  return parseUser(await apiFetch(signedUrl));
}

export async function currentUser(): Promise<User> {
  return parseUser(await apiFetch("/api/v1/me"));
}
