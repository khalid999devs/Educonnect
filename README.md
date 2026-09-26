# EduConnect

EduConnect is being rebuilt from scratch, starting from a refined product idea and PRD.

## The first version

The first version (Khalid Ahammed's KUET CSE Web Programming Lab project: a Laravel API, a
Next.js student app and a Next.js admin console) is kept unchanged in [`backup/`](backup/),
with its full Git history. Its original README is [`backup/README.md`](backup/README.md).

It still runs from inside that folder, as described in its README:

```bash
cd backup
pnpm run install:all
pnpm dev
```

Its CI workflows (`backup/.github/`) and Claude Code session hook (`backup/.claude/`) no
longer run, because GitHub and Claude Code only read those folders at the repository root.
