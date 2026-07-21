import { describe, expect, it } from "vitest";

import {
  collectionSchema,
  knowledgeItemDetailSchema,
  knowledgeItemSchema,
  knowledgePurposeFilterSchema,
} from "./second-brain";

const ITEM = {
  id: "01JKNOW00000000000000000000",
  version: 2,
  title: "Attention is all you need",
  summary: "Introduces the transformer architecture.",
  purpose: "research",
  saved: false,
  source: {
    type: "link",
    url: "https://arxiv.org/abs/1706.03762",
    resource: null,
  },
  citation: {
    authors: "Vaswani et al.",
    published_year: 2017,
    venue: "NeurIPS",
    doi: "10.5555/3295222.3295349",
  },
  tags: ["transformers", "attention"],
  collections: [
    { id: "01JCOLL00000000000000000000", name: "AI", kind: "research" },
  ],
  created_at: "2026-07-16T09:00:00Z",
  updated_at: "2026-07-16T09:00:00Z",
};

describe("second brain schemas", () => {
  it("parses a knowledge item with source and citation", () => {
    const item = knowledgeItemSchema.parse(ITEM);

    expect(item.source.type).toBe("link");
    expect(item.citation.published_year).toBe(2017);
    expect(item.tags).toContain("attention");
  });

  it("defaults collections to an empty array when omitted", () => {
    const { collections, ...withoutCollections } = ITEM;
    void collections;

    const item = knowledgeItemSchema.parse(withoutCollections);

    expect(item.collections).toEqual([]);
  });

  it("parses a detail with notes and links", () => {
    const detail = knowledgeItemDetailSchema.parse({
      ...ITEM,
      notes: [
        {
          id: "01JNOTE00000000000000000000",
          version: 1,
          body: "My own summary in my words.",
          created_at: "2026-07-16T09:00:00Z",
          updated_at: "2026-07-16T09:00:00Z",
        },
      ],
      links: [
        {
          id: "01JLINK00000000000000000000",
          direction: "outgoing",
          relation_type: "builds_on",
          item: { id: "01JKNOW00000000000000000001", title: "RNN paper" },
        },
      ],
    });

    expect(detail.notes[0]?.body).toContain("my words");
    expect(detail.links[0]?.relation_type).toBe("builds_on");
  });

  it("parses every purpose the column allows", () => {
    for (const purpose of ["resource", "study", "research", "exam"]) {
      expect(knowledgeItemSchema.parse({ ...ITEM, purpose }).purpose).toBe(
        purpose,
      );
    }
  });

  it("parses the saved bookmark flag both ways", () => {
    expect(knowledgeItemSchema.parse({ ...ITEM, saved: true }).saved).toBe(
      true,
    );
    expect(knowledgeItemSchema.parse({ ...ITEM, saved: false }).saved).toBe(
      false,
    );
  });

  it("requires the saved flag rather than defaulting it", () => {
    const { saved, ...withoutSaved } = ITEM;
    void saved;

    expect(() => knowledgeItemSchema.parse(withoutSaved)).toThrow();
  });

  it("keeps a null purpose null rather than defaulting it", () => {
    // Rows captured before purposes existed read back as null, which is not a
    // synonym for "resource" and must survive parsing unchanged.
    expect(
      knowledgeItemSchema.parse({ ...ITEM, purpose: null }).purpose,
    ).toBeNull();
  });

  it("rejects an unknown purpose", () => {
    expect(() =>
      knowledgeItemSchema.parse({ ...ITEM, purpose: "revision" }),
    ).toThrow();
  });

  it("accepts the unfiled sentinel only on the list filter", () => {
    expect(knowledgePurposeFilterSchema.parse("none")).toBe("none");
    expect(() =>
      knowledgeItemSchema.parse({ ...ITEM, purpose: "none" }),
    ).toThrow();
    expect(() => knowledgePurposeFilterSchema.parse("revision")).toThrow();
  });

  it("rejects an unknown source type", () => {
    expect(() =>
      knowledgeItemSchema.parse({
        ...ITEM,
        source: { type: "email", url: null, resource: null },
      }),
    ).toThrow();
  });

  it("parses a collection with its kind and item count", () => {
    const collection = collectionSchema.parse({
      id: "01JCOLL00000000000000000000",
      version: 1,
      name: "AI",
      description: null,
      kind: "research",
      item_count: 12,
      created_at: "2026-07-16T09:00:00Z",
      updated_at: "2026-07-16T09:00:00Z",
    });

    expect(collection.kind).toBe("research");
    expect(collection.item_count).toBe(12);
  });
});
