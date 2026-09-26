"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  EmptyState,
  ErrorState,
  FormField,
  Input,
  Skeleton,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { LogOut, MonitorSmartphone } from "lucide-react";
import { useRouter } from "next/navigation";
import { useState } from "react";

import { IconChip } from "@/components/shared/icon-chip";
import { ApiError } from "@/lib/api/http";
import {
  listSessions,
  logoutAllSessions,
  type SessionSummary,
} from "@/lib/api/settings";
import { settingsKeys } from "@/lib/query-keys";
import { useSession } from "@/providers/session-provider";

/**
 * Signed-in browsers, paired with sign-out-everywhere.
 *
 * The API returns a one-way digest in place of the session identifier and
 * never the identifier itself, because that value is the bearer credential
 * for the session. It is used only as a React key and is never rendered.
 *
 * Sign-out-everywhere ends the calling session too, so it clears the session
 * context and navigates to sign-in.
 */
export function SessionsSection() {
  const queryClient = useQueryClient();
  const router = useRouter();
  const { setUser } = useSession();
  const [confirming, setConfirming] = useState(false);
  const [password, setPassword] = useState("");

  const sessionsQuery = useQuery({
    queryKey: settingsKeys.sessions(),
    queryFn: () => listSessions(),
    staleTime: 30_000,
  });

  const logoutAllMutation = useMutation({
    mutationFn: (value: string) => logoutAllSessions(value),
    onSuccess: () => {
      setPassword("");
      queryClient.clear();
      setUser(null);
      router.push("/login");
    },
  });

  const error =
    logoutAllMutation.error instanceof ApiError
      ? logoutAllMutation.error
      : null;

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="mb-4">
          <div className="flex items-start gap-3">
            <IconChip
              icon={MonitorSmartphone}
              accent="settings"
              size="lg"
              bordered
            />
            <div className="space-y-1">
              <CardTitle as="h2">Signed-in browsers</CardTitle>
              <p className="text-body text-text-secondary">
                Every browser with a live EduConnect session, most recently
                active first. If one of these is not you, sign out everywhere
                and change your password.
              </p>
            </div>
          </div>
        </CardHeader>

        <CardContent className="space-y-2.5">
          {sessionsQuery.isPending ? (
            <>
              <Skeleton className="h-16 w-full" />
              <Skeleton className="h-16 w-full" />
            </>
          ) : sessionsQuery.isError ? (
            <ErrorState
              title="Sessions could not be loaded"
              description="Your account is unaffected. Try the request again."
              onRetry={() => void sessionsQuery.refetch()}
            />
          ) : sessionsQuery.data.length === 0 ? (
            <EmptyState
              icon={MonitorSmartphone}
              title="No sessions recorded"
              description="Sessions appear here once they have been active. This browser will show up shortly."
            />
          ) : (
            sessionsQuery.data.map((session) => (
              <SessionRow key={session.id} session={session} />
            ))
          )}
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="mb-4">
          <CardTitle as="h2">Sign out everywhere</CardTitle>
        </CardHeader>
        <CardContent className="space-y-3">
          <p className="text-body text-text-secondary">
            Ends every session listed above, including this one. You will be
            asked to sign in again.
          </p>

          {error && error.fieldError("password") === undefined ? (
            <Alert variant="error" title="You were not signed out">
              {error.message}
            </Alert>
          ) : null}

          {confirming ? (
            <form
              className="max-w-sm space-y-3"
              onSubmit={(event) => {
                event.preventDefault();
                logoutAllMutation.mutate(password);
              }}
            >
              <FormField
                label="Confirm your password"
                required
                error={error?.fieldError("password")}
                hint="Confirming your password keeps someone at your desk from locking you out."
              >
                {(control) => (
                  <Input
                    {...control}
                    type="password"
                    value={password}
                    autoComplete="current-password"
                    autoFocus
                    onChange={(event) => setPassword(event.target.value)}
                  />
                )}
              </FormField>

              <div className="flex items-center gap-2">
                <Button
                  type="submit"
                  variant="destructive"
                  disabled={password === ""}
                  isLoading={logoutAllMutation.isPending}
                  loadingLabel="Signing out"
                >
                  <LogOut aria-hidden="true" className="size-4" />
                  Sign out everywhere
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  onClick={() => {
                    setConfirming(false);
                    setPassword("");
                    logoutAllMutation.reset();
                  }}
                >
                  Cancel
                </Button>
              </div>
            </form>
          ) : (
            <Button variant="secondary" onClick={() => setConfirming(true)}>
              <LogOut aria-hidden="true" className="size-4" />
              Sign out everywhere
            </Button>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

/** Exported for test: the user agent is attacker-controlled header text. */
export function SessionRow({ session }: { session: SessionSummary }) {
  const lastActivity = new Date(session.last_activity);

  return (
    <div className="flex flex-wrap items-center gap-3 rounded-md border border-border-subtle px-3.5 py-3 transition-colors hover:border-border-strong">
      <IconChip icon={MonitorSmartphone} accent="settings" />
      <div className="min-w-0 flex-1">
        <p className="truncate text-body font-medium text-text-primary">
          {session.user_agent ?? "Unknown browser"}
        </p>
        <p className="truncate text-caption tabular-nums text-text-muted">
          {session.ip_address ?? "IP address not recorded"}
          {" · "}
          {Number.isNaN(lastActivity.getTime())
            ? "Activity not recorded"
            : lastActivity.toLocaleString(undefined, {
                dateStyle: "medium",
                timeStyle: "short",
              })}
        </p>
      </div>
      {session.is_current ? (
        <Badge variant="success">This browser</Badge>
      ) : null}
    </div>
  );
}
