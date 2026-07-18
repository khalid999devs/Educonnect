"use client";

import {
  Alert,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  Textarea,
} from "@educonnect/ui";
import { useMutation, useQueryClient } from "@tanstack/react-query";
import { useState } from "react";

import { seedDemoContent } from "@/lib/api/admin-analytics";
import { ApiError } from "@/lib/api/http";
import { contentKeys } from "@/lib/query-keys";
import { useStepUp } from "@/providers/step-up-provider";

export function DemoDataView() {
  const queryClient = useQueryClient();
  const [reason, setReason] = useState("");
  const { runWithStepUp } = useStepUp();

  const mutation = useMutation({
    mutationFn: () => runWithStepUp(() => seedDemoContent(reason.trim())),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: contentKeys.all });
    },
  });

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Demo data</h1>
        <p className="text-body text-text-secondary">
          Seed the deterministic guidance launch catalog. This is additive and
          idempotent — it only fills in the curated catalog when it is absent
          and never deletes existing data.
        </p>
      </header>

      <Card>
        <CardHeader>
          <CardTitle>Seed launch content</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {mutation.isError ? (
            <Alert variant="error" title="Could not seed">
              {mutation.error instanceof ApiError
                ? mutation.error.message
                : "Something went wrong."}
            </Alert>
          ) : null}
          {mutation.isSuccess ? (
            <Alert variant="success" title="Catalog ready">
              The catalog now has {mutation.data.tools} tools,{" "}
              {mutation.data.prompts} prompts, and {mutation.data.workflows}{" "}
              workflows.
            </Alert>
          ) : null}
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
          <Button
            isLoading={mutation.isPending}
            disabled={reason.trim().length === 0}
            onClick={() => mutation.mutate()}
          >
            Seed launch content
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
