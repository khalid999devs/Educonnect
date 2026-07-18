"use client";

import { Alert, Button, Dialog, FormField, Input } from "@educonnect/ui";
import {
  createContext,
  useCallback,
  useContext,
  useRef,
  useState,
  type FormEvent,
  type ReactNode,
} from "react";

import { reauthenticate } from "@/lib/api/admin-auth";
import { ApiError } from "@/lib/api/http";

type StepUpContextValue = {
  /**
   * Runs an action; if it fails with a 423 re-authentication requirement, it
   * prompts for a password confirmation and retries the action exactly once.
   * A cancelled prompt surfaces the original error unchanged.
   */
  runWithStepUp: <T>(action: () => Promise<T>) => Promise<T>;
};

const StepUpContext = createContext<StepUpContextValue | null>(null);

const CANCELLED = Symbol("step-up-cancelled");

type PendingConfirmation = {
  resolve: () => void;
  reject: (reason: unknown) => void;
};

/**
 * Provides step-up re-authentication for the highest-risk console actions. The
 * backend rejects those actions with 423 REAUTHENTICATION_REQUIRED unless a
 * fresh password confirmation exists in the session; this provider turns that
 * into a password prompt and a transparent retry, so an unattended or hijacked
 * session cannot suspend accounts, change roles, or seed data without the
 * current password.
 */
export function StepUpProvider({ children }: { children: ReactNode }) {
  const [open, setOpen] = useState(false);
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const pendingRef = useRef<PendingConfirmation | null>(null);

  const reset = useCallback(() => {
    setOpen(false);
    setPassword("");
    setError(null);
    setSubmitting(false);
  }, []);

  const confirmStepUp = useCallback(() => {
    return new Promise<void>((resolve, reject) => {
      pendingRef.current = { resolve, reject };
      setPassword("");
      setError(null);
      setOpen(true);
    });
  }, []);

  const cancel = useCallback(() => {
    const pending = pendingRef.current;
    pendingRef.current = null;
    reset();
    pending?.reject(CANCELLED);
  }, [reset]);

  const submit = useCallback(
    async (event: FormEvent) => {
      event.preventDefault();

      if (submitting) {
        return;
      }

      setSubmitting(true);
      setError(null);

      try {
        await reauthenticate(password);
        const pending = pendingRef.current;
        pendingRef.current = null;
        reset();
        pending?.resolve();
      } catch (caught) {
        setSubmitting(false);
        setError(
          caught instanceof ApiError
            ? caught.message
            : "Could not confirm your password.",
        );
      }
    },
    [password, submitting, reset],
  );

  const runWithStepUp = useCallback(
    async <T,>(action: () => Promise<T>): Promise<T> => {
      try {
        return await action();
      } catch (caught) {
        if (caught instanceof ApiError && caught.status === 423) {
          try {
            await confirmStepUp();
          } catch {
            // Cancelled: surface the original re-authentication requirement.
            throw caught;
          }

          return action();
        }

        throw caught;
      }
    },
    [confirmStepUp],
  );

  return (
    <StepUpContext.Provider value={{ runWithStepUp }}>
      {children}
      <Dialog open={open} title="Confirm it's you" size="sm" onClose={cancel}>
        <form onSubmit={submit} className="space-y-4" noValidate>
          <p className="text-body text-text-secondary">
            This is a high-risk action. Re-enter your administrator password to
            continue.
          </p>
          {error ? (
            <Alert variant="error" title="Confirmation failed">
              {error}
            </Alert>
          ) : null}
          <FormField label="Password" required>
            {(control) => (
              <Input
                type="password"
                autoComplete="current-password"
                value={password}
                onChange={(event) => setPassword(event.target.value)}
                {...control}
              />
            )}
          </FormField>
          <div className="flex justify-end gap-2">
            <Button type="button" variant="ghost" onClick={cancel}>
              Cancel
            </Button>
            <Button
              type="submit"
              isLoading={submitting}
              disabled={password.length === 0}
            >
              Confirm
            </Button>
          </div>
        </form>
      </Dialog>
    </StepUpContext.Provider>
  );
}

export function useStepUp(): StepUpContextValue {
  const context = useContext(StepUpContext);

  if (context === null) {
    throw new Error("useStepUp must be used within a StepUpProvider.");
  }

  return context;
}
