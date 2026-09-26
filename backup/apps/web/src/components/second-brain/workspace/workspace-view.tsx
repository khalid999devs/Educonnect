"use client";

import { Badge, ErrorState, Skeleton } from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import { FileText, MessagesSquare, Wand2, X } from "lucide-react";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";

import { SectionTabs } from "@/components/shared/section-tabs";
import { getKnowledgeItem } from "@/lib/api/second-brain";
import { brainKeys } from "@/lib/query-keys";
import { purposeDescriptor } from "../purpose";
import { ActionsPane } from "./actions-pane";
import { ChatPane } from "./chat-pane";
import { DocumentPane } from "./document-pane";

type Pane = "document" | "chat" | "actions";

const PANE_TABS = [
  { value: "document" as const, label: "Document", icon: FileText },
  { value: "chat" as const, label: "Ask", icon: MessagesSquare },
  { value: "actions" as const, label: "Actions", icon: Wand2 },
];

/**
 * The full-screen focused workspace: document and its extracted text on the
 * left, the document-aware chat and the purpose-driven ready actions on the
 * right.
 *
 * Below `lg` the split collapses to one pane at a time behind a tab bar, which
 * is what makes it genuinely usable on a phone rather than a desktop layout
 * squeezed into a narrow column. Above `lg` all three are visible at once.
 *
 * Every pane owns its own scroll container. The page body never scrolls: a
 * 200,000-character document must not be able to push the exit control off
 * screen.
 */
export function WorkspaceView({ itemId }: { itemId: string }) {
  const searchParams = useSearchParams();
  const [pane, setPane] = useState<Pane>("document");

  const itemQuery = useQuery({
    queryKey: brainKeys.item(itemId),
    queryFn: () => getKnowledgeItem(itemId),
  });

  const item = itemQuery.data ?? null;

  /** The capture flow hands the intake id forward in the URL. Once the API
   * resource exposes `source.intake_item_id` (see the patch spec) the stored
   * value takes over and deep links no longer need the parameter. */
  const intakeItemId =
    item?.source.intake_item_id ?? searchParams.get("intake") ?? null;

  const purpose = purposeDescriptor(item?.purpose ?? null);

  if (itemQuery.isPending) {
    return (
      <div className="space-y-3 p-4">
        <Skeleton className="h-12 rounded-lg" />
        <div className="grid gap-3 lg:grid-cols-2">
          <Skeleton className="h-96 rounded-lg" />
          <Skeleton className="h-96 rounded-lg" />
        </div>
      </div>
    );
  }

  if (itemQuery.isError || !item) {
    return (
      <div className="p-6">
        <ErrorState
          title="This workspace couldn't open"
          onRetry={() => void itemQuery.refetch()}
        />
      </div>
    );
  }

  return (
    <div className="flex h-dvh min-h-0 flex-col overflow-hidden bg-bg-canvas">
      <header className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border-default bg-bg-surface px-4 py-2.5 sm:px-5">
        <div className="flex min-w-0 items-center gap-2.5">
          <h1 className="truncate text-body font-medium text-text-primary">
            {item.title}
          </h1>
          {purpose ? <Badge variant="research">{purpose.label}</Badge> : null}
        </div>

        <Link
          href={`/second-brain/${itemId}`}
          className="flex items-center gap-1.5 rounded-md border border-border-default px-3 py-1.5 text-button text-text-secondary transition-colors hover:border-border-strong hover:text-text-primary focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-focus"
        >
          <X aria-hidden="true" className="size-4" />
          Close workspace
        </Link>
      </header>

      {/* Below lg: one pane at a time. */}
      <div className="shrink-0 px-2 lg:hidden">
        <SectionTabs
          label="Workspace panes"
          tabs={PANE_TABS}
          value={pane}
          onChange={setPane}
          panelId={(value) => `workspace-pane-${value}`}
        />
      </div>

      <div className="grid min-h-0 flex-1 lg:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
        <section
          id="workspace-pane-document"
          aria-label="Document"
          className={`min-h-0 border-border-default lg:block lg:border-r ${
            pane === "document" ? "block" : "hidden"
          }`}
        >
          <DocumentPane item={item} intakeItemId={intakeItemId} />
        </section>

        <div className="grid min-h-0 lg:grid-rows-[minmax(0,1fr)_minmax(0,1fr)]">
          <section
            id="workspace-pane-actions"
            aria-label="Ready actions"
            className={`min-h-0 border-border-default lg:block lg:border-b ${
              pane === "actions" ? "block" : "hidden"
            }`}
          >
            <ActionsPane itemId={itemId} purpose={item.purpose} />
          </section>

          <section
            id="workspace-pane-chat"
            aria-label="Ask this document"
            className={`min-h-0 lg:block ${pane === "chat" ? "block" : "hidden"}`}
          >
            <ChatPane itemId={itemId} documentTitle={item.title} />
          </section>
        </div>
      </div>
    </div>
  );
}
