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

import { currentUser } from "@/lib/api/auth";
import { ApiError } from "@/lib/api/http";
import type { User } from "@/lib/api/schemas";

export type SessionStatus = "loading" | "authenticated" | "guest";

type SessionContextValue = {
  status: SessionStatus;
  user: User | null;
  /** Replace the session user after login/register/verify. */
  setUser: (user: User | null) => void;
  /** Re-fetch /me (e.g. after verification). */
  refresh: () => Promise<User | null>;
};

const SessionContext = createContext<SessionContextValue | null>(null);

export function SessionProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<SessionStatus>("loading");
  const [user, setUserState] = useState<User | null>(null);

  const setUser = useCallback((next: User | null) => {
    setUserState(next);
    setStatus(next === null ? "guest" : "authenticated");
  }, []);

  const refresh = useCallback(async (): Promise<User | null> => {
    try {
      const me = await currentUser();
      setUser(me);

      return me;
    } catch (error) {
      if (error instanceof ApiError && error.status === 401) {
        setUser(null);

        return null;
      }

      setUser(null);

      return null;
    }
  }, [setUser]);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  const value = useMemo(
    () => ({ status, user, setUser, refresh }),
    [status, user, setUser, refresh],
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
