"use client";

import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from "react";

import { currentAdmin } from "@/lib/api/admin-auth";
import { ApiError } from "@/lib/api/http";
import type { AdminSession } from "@/lib/api/schemas";

export type SessionStatus = "loading" | "authenticated" | "guest";

type SessionContextValue = {
  status: SessionStatus;
  session: AdminSession | null;
  /** Replace the session after a login, or clear it on sign-out. */
  setSession: (session: AdminSession | null) => void;
  /** Re-fetch /admin/me; returns null when the session is no longer admin. */
  refresh: () => Promise<AdminSession | null>;
  /** True when the session holds the given capability key. */
  can: (capability: string) => boolean;
};

const SessionContext = createContext<SessionContextValue | null>(null);

/**
 * Client-side mirror of the admin session. The API guard is always
 * authoritative - this context only shapes navigation and affordances. Both
 * 401 (no session) and 403 (capability revoked while signed in) collapse to
 * the guest state so a demoted operator is treated as signed out.
 */
export function SessionProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<SessionStatus>("loading");
  const [session, setSessionState] = useState<AdminSession | null>(null);

  const setSession = useCallback((next: AdminSession | null) => {
    setSessionState(next);
    setStatus(next === null ? "guest" : "authenticated");
  }, []);

  const refresh = useCallback(async (): Promise<AdminSession | null> => {
    try {
      const next = await currentAdmin();
      setSession(next);

      return next;
    } catch (error) {
      if (
        error instanceof ApiError &&
        (error.status === 401 || error.status === 403)
      ) {
        setSession(null);

        return null;
      }

      setSession(null);

      return null;
    }
  }, [setSession]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const can = useCallback(
    (capability: string): boolean =>
      session?.authorization.capabilities.includes(capability) ?? false,
    [session],
  );

  const value = useMemo(
    () => ({ status, session, setSession, refresh, can }),
    [status, session, setSession, refresh, can],
  );

  return (
    <SessionContext.Provider value={value}>{children}</SessionContext.Provider>
  );
}

export function useSession(): SessionContextValue {
  const context = useContext(SessionContext);

  if (context === null) {
    throw new Error("useSession must be used inside a SessionProvider.");
  }

  return context;
}
