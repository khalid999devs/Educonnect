"use client";

import { Button, Dialog } from "@educonnect/ui";

export type ConfirmDialogProps = {
  open: boolean;
  title: string;
  description: string;
  confirmLabel: string;
  busy: boolean;
  error?: string | null;
  onConfirm: () => void;
  onCancel: () => void;
};

/** Destructive-action confirmation with explicit consequence copy. */
export function ConfirmDialog({
  open,
  title,
  description,
  confirmLabel,
  busy,
  error,
  onConfirm,
  onCancel,
}: ConfirmDialogProps) {
  return (
    <Dialog
      open={open}
      onClose={busy ? () => undefined : onCancel}
      title={title}
      size="sm"
      footer={
        <>
          <Button variant="ghost" onClick={onCancel} disabled={busy}>
            Keep it
          </Button>
          <Button
            variant="destructive"
            onClick={onConfirm}
            isLoading={busy}
            loadingLabel="Working"
          >
            {confirmLabel}
          </Button>
        </>
      }
    >
      <p className="text-body text-text-secondary">{description}</p>
      {error ? (
        <p className="mt-2 text-caption text-status-error">{error}</p>
      ) : null}
    </Dialog>
  );
}
