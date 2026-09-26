"use client";

import {
  Alert,
  Button,
  Dialog,
  FormField,
  Select,
  Textarea,
} from "@educonnect/ui";
import { useEffect, useState } from "react";

import type { ReportReason } from "@/lib/api/community";

const REASONS: Array<{ value: ReportReason; label: string }> = [
  { value: "spam", label: "Spam or advertising" },
  { value: "harassment", label: "Harassment or abuse" },
  { value: "off_topic", label: "Off topic" },
  { value: "safety", label: "Safety concern" },
  { value: "other", label: "Something else" },
];

export function ReportDialog({
  open,
  subjectLabel,
  isSubmitting,
  error,
  onClose,
  onSubmit,
}: {
  open: boolean;
  subjectLabel: "post" | "comment";
  isSubmitting: boolean;
  error: string | null;
  onClose: () => void;
  onSubmit: (reason: ReportReason, detail: string | null) => void;
}) {
  const [reason, setReason] = useState<ReportReason>("spam");
  const [detail, setDetail] = useState("");

  useEffect(() => {
    if (open) {
      setReason("spam");
      setDetail("");
    }
  }, [open]);

  return (
    <Dialog
      open={open}
      onClose={onClose}
      title={`Report this ${subjectLabel}`}
      description="Reports are sent to the community moderators. Nothing is shared publicly."
      footer={
        <>
          <Button variant="ghost" onClick={onClose} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button
            variant="destructive"
            isLoading={isSubmitting}
            loadingLabel="Sending"
            onClick={() =>
              onSubmit(reason, detail.trim() === "" ? null : detail.trim())
            }
          >
            Send report
          </Button>
        </>
      }
    >
      <div className="flex flex-col gap-4">
        {error ? (
          <Alert variant="error" title="Report failed">
            {error}
          </Alert>
        ) : null}
        <FormField id="report-reason" label="Reason" required>
          {(control) => (
            <Select
              {...control}
              value={reason}
              onChange={(event) =>
                setReason(event.target.value as ReportReason)
              }
            >
              {REASONS.map((item) => (
                <option key={item.value} value={item.value}>
                  {item.label}
                </option>
              ))}
            </Select>
          )}
        </FormField>
        <FormField
          id="report-detail"
          label="Add context"
          hint="Optional: up to 1000 characters."
        >
          {(control) => (
            <Textarea
              {...control}
              value={detail}
              maxLength={1000}
              rows={3}
              onChange={(event) => setDetail(event.target.value)}
              placeholder="What's wrong with this content?"
            />
          )}
        </FormField>
      </div>
    </Dialog>
  );
}
