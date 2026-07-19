"use client";

import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  ErrorState,
  FormField,
  Skeleton,
  Textarea,
} from "@educonnect/ui";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
  ArrowLeft,
  ExternalLink,
  FileText,
  Link2,
  Link as LinkIcon,
  Pencil,
  StickyNote,
  Trash2,
} from "lucide-react";
import Link from "next/link";
import { useState } from "react";

import { ApiError } from "@/lib/api/http";
import {
  addKnowledgeNote,
  deleteKnowledgeNote,
  getKnowledgeItem,
  updateKnowledgeNote,
  type KnowledgeNote,
} from "@/lib/api/second-brain";
import { brainKeys } from "@/lib/query-keys";

const RELATION_LABELS: Record<string, string> = {
  related: "Related to",
  supports: "Supports",
  contradicts: "Contradicts",
  builds_on: "Builds on",
};

export function ItemDetail({ itemId }: { itemId: string }) {
  const queryClient = useQueryClient();
  const [draftNote, setDraftNote] = useState("");
  const [editingNote, setEditingNote] = useState<KnowledgeNote | null>(null);
  const [editBody, setEditBody] = useState("");

  const itemQuery = useQuery({
    queryKey: brainKeys.item(itemId),
    queryFn: () => getKnowledgeItem(itemId),
  });

  const invalidate = () => {
    void queryClient.invalidateQueries({ queryKey: brainKeys.item(itemId) });
    void queryClient.invalidateQueries({ queryKey: brainKeys.all });
  };

  const addNote = useMutation({
    mutationFn: () => addKnowledgeNote(itemId, draftNote.trim()),
    onSuccess: () => {
      setDraftNote("");
      invalidate();
    },
  });

  const editNote = useMutation({
    mutationFn: (note: KnowledgeNote) =>
      updateKnowledgeNote(itemId, note.id, {
        body: editBody.trim(),
        expected_version: note.version,
      }),
    onSuccess: () => {
      setEditingNote(null);
      invalidate();
    },
  });

  const removeNote = useMutation({
    mutationFn: (noteId: string) => deleteKnowledgeNote(itemId, noteId),
    onSuccess: invalidate,
  });

  if (itemQuery.isPending) {
    return <Skeleton className="h-96 rounded-lg" />;
  }

  if (itemQuery.isError) {
    return (
      <ErrorState
        title="This item couldn't load"
        onRetry={() => void itemQuery.refetch()}
      />
    );
  }

  const item = itemQuery.data;

  return (
    <div className="space-y-4">
      <Link
        href="/second-brain"
        className="inline-flex items-center gap-1.5 text-body text-brand-primary hover:underline"
      >
        <ArrowLeft aria-hidden="true" className="size-4" />
        Back to Second Brain
      </Link>

      <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_20rem] xl:items-start">
        <div className="space-y-4">
          <Card>
            <CardHeader className="space-y-2">
              {/* All text below is source-derived and rendered as escaped
                  React text - a prompt-injection payload cannot execute. */}
              <CardTitle className="text-h3">{item.title}</CardTitle>
              {item.citation.authors ||
              item.citation.venue ||
              item.citation.published_year ? (
                <p className="text-body text-text-secondary">
                  {[
                    item.citation.authors,
                    item.citation.venue,
                    item.citation.published_year
                      ? String(item.citation.published_year)
                      : null,
                  ]
                    .filter(Boolean)
                    .join(" · ")}
                </p>
              ) : null}
              <SourceLine source={item.source} doi={item.citation.doi} />
            </CardHeader>
            {item.summary ? (
              <CardContent>
                <p className="whitespace-pre-wrap text-body text-text-secondary">
                  {item.summary}
                </p>
              </CardContent>
            ) : null}
          </Card>

          {/* Notes */}
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2">
                <StickyNote
                  aria-hidden="true"
                  className="size-5 text-brand-primary"
                />
                Notes
              </CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <form
                onSubmit={(event) => {
                  event.preventDefault();
                  addNote.mutate();
                }}
                className="space-y-2"
              >
                <FormField label="Add a note">
                  {(control) => (
                    <Textarea
                      {...control}
                      value={draftNote}
                      maxLength={8000}
                      onChange={(event) => setDraftNote(event.target.value)}
                      placeholder="Your own words about this source…"
                      className="min-h-20"
                    />
                  )}
                </FormField>
                {addNote.error ? (
                  <Alert variant="error" title="Couldn't add the note">
                    {addNote.error.message}
                  </Alert>
                ) : null}
                <Button
                  type="submit"
                  size="sm"
                  isLoading={addNote.isPending}
                  loadingLabel="Adding"
                  disabled={draftNote.trim() === ""}
                >
                  Add note
                </Button>
              </form>

              {item.notes.length === 0 ? (
                <p className="text-caption text-text-muted">No notes yet.</p>
              ) : (
                <ul className="space-y-2">
                  {item.notes.map((note) => (
                    <li
                      key={note.id}
                      className="rounded-md border border-border-subtle bg-bg-subtle/60 p-3"
                    >
                      {editingNote?.id === note.id ? (
                        <div className="space-y-2">
                          <Textarea
                            value={editBody}
                            maxLength={8000}
                            onChange={(event) =>
                              setEditBody(event.target.value)
                            }
                            className="min-h-20"
                            aria-label="Edit note"
                          />
                          {editNote.error instanceof ApiError &&
                          editNote.error.status === 409 ? (
                            <Alert variant="error" title="This note changed">
                              Reopen it to load the latest version.
                            </Alert>
                          ) : null}
                          <div className="flex gap-2">
                            <Button
                              size="sm"
                              isLoading={editNote.isPending}
                              loadingLabel="Saving"
                              disabled={editBody.trim() === ""}
                              onClick={() => editNote.mutate(note)}
                            >
                              Save
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => setEditingNote(null)}
                            >
                              Cancel
                            </Button>
                          </div>
                        </div>
                      ) : (
                        <>
                          <p className="whitespace-pre-wrap text-body text-text-primary">
                            {note.body}
                          </p>
                          <div className="mt-2 flex gap-1">
                            <Button
                              variant="ghost"
                              size="sm"
                              aria-label="Edit note"
                              onClick={() => {
                                editNote.reset();
                                setEditBody(note.body);
                                setEditingNote(note);
                              }}
                            >
                              <Pencil aria-hidden="true" className="size-4" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              aria-label="Delete note"
                              className="text-status-error"
                              disabled={removeNote.isPending}
                              onClick={() => removeNote.mutate(note.id)}
                            >
                              <Trash2 aria-hidden="true" className="size-4" />
                            </Button>
                          </div>
                        </>
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Side rail: tags, collections, connections, topics */}
        <div className="space-y-4">
          {item.tags.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-h4">Tags</CardTitle>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-1.5">
                {item.tags.map((tag) => (
                  <Badge key={tag} variant="neutral">
                    {tag}
                  </Badge>
                ))}
              </CardContent>
            </Card>
          ) : null}

          {item.collections && item.collections.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-h4">Collections</CardTitle>
              </CardHeader>
              <CardContent className="flex flex-wrap gap-1.5">
                {item.collections.map((collection) => (
                  <Badge key={collection.id} variant="brand">
                    {collection.name}
                  </Badge>
                ))}
              </CardContent>
            </Card>
          ) : null}

          {item.links.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle className="flex items-center gap-2 text-h4">
                  <LinkIcon aria-hidden="true" className="size-4" />
                  Connections
                </CardTitle>
              </CardHeader>
              <CardContent>
                <ul className="space-y-1.5">
                  {item.links.map((link) => (
                    <li key={link.id} className="text-body">
                      <span className="text-caption text-text-muted">
                        {RELATION_LABELS[link.relation_type] ??
                          link.relation_type}
                        {link.direction === "incoming" ? " (incoming)" : ""}
                        :{" "}
                      </span>
                      {link.item ? (
                        <Link
                          href={`/second-brain/${link.item.id}`}
                          className="text-brand-primary hover:underline"
                        >
                          {link.item.title}
                        </Link>
                      ) : (
                        <span className="text-text-muted">Unavailable</span>
                      )}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </Card>
          ) : null}

          {item.research_topics.length > 0 ? (
            <Card>
              <CardHeader>
                <CardTitle className="text-h4">Research topics</CardTitle>
              </CardHeader>
              <CardContent className="space-y-1.5">
                {item.research_topics.map((topic) => (
                  <Link
                    key={topic.id}
                    href={`/research/${topic.id}`}
                    className="flex items-center justify-between gap-2 text-body text-brand-primary hover:underline"
                  >
                    {topic.title}
                    {topic.reading_status ? (
                      <Badge variant="neutral">
                        {topic.reading_status.replace("_", " ")}
                      </Badge>
                    ) : null}
                  </Link>
                ))}
              </CardContent>
            </Card>
          ) : null}
        </div>
      </div>
    </div>
  );
}

function SourceLine({
  source,
  doi,
}: {
  source: {
    type: "resource" | "link" | "none";
    url: string | null;
    resource: { id: string; title: string } | null;
  };
  doi: string | null;
}) {
  if (source.type === "link" && source.url) {
    return (
      <a
        href={source.url}
        target="_blank"
        rel="noopener noreferrer"
        className="inline-flex items-center gap-1.5 text-body text-brand-primary hover:underline"
      >
        <Link2 aria-hidden="true" className="size-4" />
        {/* URL rendered as text; https-only per contract. */}
        <span className="break-all">{source.url}</span>
        <ExternalLink aria-hidden="true" className="size-3" />
      </a>
    );
  }

  if (source.type === "resource" && source.resource) {
    return (
      <span className="inline-flex items-center gap-1.5 text-body text-text-secondary">
        <FileText aria-hidden="true" className="size-4 text-text-muted" />
        From your file: {source.resource.title}
      </span>
    );
  }

  return (
    <span className="text-body text-text-muted">
      {doi ? `DOI: ${doi}` : "No linked source"}
    </span>
  );
}
