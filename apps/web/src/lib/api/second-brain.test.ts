import { describe, expect, it } from "vitest";

import {
  collectionSchema,
  knowledgeItemDetailSchema,
  knowledgeItemSchema,
} from "./second-brain";

const ITEM = {
  id: "01JKNOW00000000000000000000",
  version: 2,
  title: "Attention is all you need",
  summary: "Introduces the transformer architecture.",
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

  it("parses a detail with notes, links, and research topics", () => {
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
      research_topics: [
        {
          id: "01JTOPIC0000000000000000000",
          title: "Interpretability",
          reading_status: "reading",
        },
      ],
    });

    expect(detail.notes[0]?.body).toContain("my words");
    expect(detail.links[0]?.relation_type).toBe("builds_on");
    expect(detail.research_topics[0]?.reading_status).toBe("reading");
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
