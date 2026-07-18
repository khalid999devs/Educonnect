"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Dialog,
  ErrorState,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import Link from "next/link";
import { useState } from "react";

import {
  changeUserRoles,
  getUser,
  reactivateUser,
  ROLE_KEYS,
  suspendUser,
} from "@/lib/api/admin-users";
import { ApiError } from "@/lib/api/http";
import { formatDateTime, humanizeKey } from "@/lib/format";
import { userKeys } from "@/lib/query-keys";
import { useSession } from "@/providers/session-provider";
import { useStepUp } from "@/providers/step-up-provider";

export function UserDetailView({ userId }: { userId: string }) {
  const queryClient = useQueryClient();
  const { can, session } = useSession();
  const { runWithStepUp } = useStepUp();
  const [dialog, setDialog] = useState<
    "suspend" | "reactivate" | "roles" | null
  >(null);

  const query = useQuery({
    queryKey: userKeys.detail(userId),
    queryFn: () => getUser(userId),
  });

  const closeAndRefresh = () => {
    setDialog(null);
    void queryClient.invalidateQueries({ queryKey: userKeys.all });
  };

  if (query.isPending) {
    return <Skeleton className="h-64 w-full" />;
  }

  if (query.isError) {
    return (
      <ErrorState
        title="Could not load this user"
        description="The account could not be loaded."
        onRetry={() => void query.refetch()}
      />
    );
  }

  const user = query.data;
  const canSuspend = can("users.suspend");
  const canAssignRoles = can("authorization.roles-assign");
  const isSelf = session?.user.id === user.id;

  return (
    <div className="space-y-6">
      <div>
        <Link
          href="/users"
          className="text-caption text-brand-primary hover:underline"
        >
          ← Back to users
        </Link>
        <h1 className="mt-2 text-h2 text-text-primary">{user.name}</h1>
        <p className="text-body text-text-secondary">{user.email}</p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Account</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 sm:grid-cols-2">
          <Field label="Status">
            <Badge variant={user.status === "active" ? "success" : "error"}>
              {humanizeKey(user.status)}
            </Badge>
            {user.suspended_at ? (
              <span className="ml-2 text-caption text-text-muted">
                since {formatDateTime(user.suspended_at)}
              </span>
            ) : null}
          </Field>
          <Field label="Email verified">
            <Badge variant={user.email_verified ? "success" : "warning"}>
              {user.email_verified ? "Verified" : "Unverified"}
            </Badge>
          </Field>
          <Field label="Roles">
            <span className="flex flex-wrap gap-1">
              {user.roles.map((role) => (
                <Badge key={role} variant="brand">
                  {humanizeKey(role)}
                </Badge>
              ))}
            </span>
          </Field>
          <Field label="Joined">{formatDateTime(user.created_at)}</Field>
          <Field label="Last sign-in">
            {formatDateTime(user.last_login_at)}
          </Field>
        </CardContent>
      </Card>

      <div className="flex flex-wrap gap-3">
        {canSuspend && !isSelf ? (
          user.status === "active" ? (
            <Button variant="destructive" onClick={() => setDialog("suspend")}>
              Suspend account
            </Button>
          ) : (
            <Button variant="secondary" onClick={() => setDialog("reactivate")}>
              Reactivate account
            </Button>
          )
        ) : null}
        {canAssignRoles && !isSelf ? (
          <Button variant="secondary" onClick={() => setDialog("roles")}>
            Edit roles
          </Button>
        ) : null}
        {isSelf ? (
          <p className="text-caption text-text-muted">
            You cannot suspend or change the roles of your own account.
          </p>
        ) : null}
      </div>

      {dialog === "suspend" ? (
        <ReasonDialog
          title="Suspend account"
          description={`Suspending ${user.name} immediately ends their sessions and blocks sign-in. This is recorded in the audit log.`}
          confirmLabel="Suspend"
          confirmVariant="destructive"
          onClose={() => setDialog(null)}
          onConfirm={(reason) =>
            runWithStepUp(() => suspendUser(user.id, reason))
          }
          onDone={closeAndRefresh}
        />
      ) : null}

      {dialog === "reactivate" ? (
        <ReasonDialog
          title="Reactivate account"
          description={`Reactivating ${user.name} restores their ability to sign in. This is recorded in the audit log.`}
          confirmLabel="Reactivate"
          confirmVariant="primary"
          onClose={() => setDialog(null)}
          onConfirm={(reason) =>
            runWithStepUp(() => reactivateUser(user.id, reason))
          }
          onDone={closeAndRefresh}
        />
      ) : null}

      {dialog === "roles" ? (
        <RolesDialog
          currentRoles={user.roles}
          onClose={() => setDialog(null)}
          onConfirm={(roles, reason) =>
            runWithStepUp(() => changeUserRoles(user.id, roles, reason))
          }
          onDone={closeAndRefresh}
        />
      ) : null}
    </div>
  );
}

function Field({
  label,
  children,
}: {
  label: string;
  children: React.ReactNode;
}) {
  return (
    <div>
      <p className="text-caption font-medium uppercase tracking-wide text-text-muted">
        {label}
      </p>
      <div className="mt-1 text-body text-text-primary">{children}</div>
    </div>
  );
}

function ReasonDialog({
  title,
  description,
  confirmLabel,
  confirmVariant,
  onClose,
  onConfirm,
  onDone,
}: {
  title: string;
  description: string;
  confirmLabel: string;
  confirmVariant: "destructive" | "primary";
  onClose: () => void;
  onConfirm: (reason: string) => Promise<unknown>;
  onDone: () => void;
}) {
  const [reason, setReason] = useState("");
  const mutation = useMutation({
    mutationFn: () => onConfirm(reason.trim()),
    onSuccess: onDone,
  });

  return (
    <Dialog open title={title} onClose={onClose}>
      <div className="space-y-4">
        <p className="text-body text-text-secondary">{description}</p>
        {mutation.isError ? (
          <Alert variant="error" title="Action failed">
            {mutation.error instanceof ApiError
              ? mutation.error.message
              : "Something went wrong."}
          </Alert>
        ) : null}
        <label className="block">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Reason (recorded in the audit log)
          </span>
          <Textarea
            rows={3}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            variant={confirmVariant}
            isLoading={mutation.isPending}
            disabled={reason.trim().length === 0}
            onClick={() => mutation.mutate()}
          >
            {confirmLabel}
          </Button>
        </div>
      </div>
    </Dialog>
  );
}

function RolesDialog({
  currentRoles,
  onClose,
  onConfirm,
  onDone,
}: {
  currentRoles: string[];
  onClose: () => void;
  onConfirm: (roles: string[], reason: string) => Promise<unknown>;
  onDone: () => void;
}) {
  const [selected, setSelected] = useState<string[]>(currentRoles);
  const [reason, setReason] = useState("");
  const mutation = useMutation({
    mutationFn: () => onConfirm(selected, reason.trim()),
    onSuccess: onDone,
  });

  const toggle = (role: string) =>
    setSelected((prev) =>
      prev.includes(role) ? prev.filter((r) => r !== role) : [...prev, role],
    );

  return (
    <Dialog open title="Edit roles" onClose={onClose}>
      <div className="space-y-4">
        {mutation.isError ? (
          <Alert variant="error" title="Could not update roles">
            {mutation.error instanceof ApiError
              ? mutation.error.message
              : "Something went wrong."}
          </Alert>
        ) : null}
        <fieldset className="space-y-2">
          <legend className="text-caption font-medium text-text-secondary">
            Roles
          </legend>
          {ROLE_KEYS.map((role) => (
            <label key={role} className="flex items-center gap-2 text-body">
              <input
                type="checkbox"
                checked={selected.includes(role)}
                onChange={() => toggle(role)}
                className="size-4 accent-brand-primary"
              />
              {humanizeKey(role)}
            </label>
          ))}
        </fieldset>
        <label className="block">
          <span className="mb-1 block text-caption font-medium text-text-secondary">
            Reason (recorded in the audit log)
          </span>
          <Textarea
            rows={2}
            value={reason}
            onChange={(event) => setReason(event.target.value)}
          />
        </label>
        <div className="flex justify-end gap-2">
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            isLoading={mutation.isPending}
            disabled={selected.length === 0 || reason.trim().length === 0}
            onClick={() => mutation.mutate()}
          >
            Save roles
          </Button>
        </div>
      </div>
    </Dialog>
  );
}
