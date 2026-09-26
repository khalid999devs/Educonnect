import { z } from "zod";

/** Runtime schemas for the admin API responses this console consumes (doc 07). */

export const userSchema = z.object({
  id: z.string().min(1),
  name: z.string(),
  email: z.string(),
  email_verified: z.boolean(),
  primary_role: z.string(),
});

export type User = z.infer<typeof userSchema>;

/**
 * The admin session envelope (App\Http\Resources\AdminSessionResource): the
 * authenticated user plus the flattened, de-duplicated, sorted roles and
 * capabilities that drive permission-aware navigation. The API is always
 * authoritative; these only shape what the console offers.
 */
export const adminSessionSchema = z.object({
  user: userSchema,
  authorization: z.object({
    roles: z.array(z.string()),
    capabilities: z.array(z.string()),
  }),
});

export type AdminSession = z.infer<typeof adminSessionSchema>;
