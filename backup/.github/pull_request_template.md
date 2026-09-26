# Pull Request

## Problem and solution

Describe the problem, the bounded solution, and why this change is needed.

## Phase and requirements

- Phase:
- Requirement IDs:
- Related specification or ADR:

## Scope

### In scope

-

### Explicit non-goals

-

## Impact review

- Architecture:
- API/contracts:
- Database/data integrity:
- Authentication/authorization:
- Security/privacy:
- Performance/reliability:
- Observability/operations:

## Validation evidence

List exact commands and results. Do not write “passes” without command output.

```text
command:
result:
```

## Migrations and deployment

- Migration/data impact:
- Deployment ordering:
- Backfill/lock-time considerations:
- Rollback or forward-recovery plan:

## UI evidence

For visible changes, include dark/light and supported responsive widths. Record loading, empty, error, success, disabled, and permission-denied states as applicable.

## Checklist

- [ ] Scope is small, phase-owned, and reviewable
- [ ] Existing behavior and data are preserved or migrated safely
- [ ] Backend authorization and denial paths are tested where applicable
- [ ] Validation and stable error behavior are tested
- [ ] Queries are bounded and indexed; no N+1 query was introduced
- [ ] Tests, formatting, lint/static analysis, type checks, and builds pass as applicable
- [ ] Dependency and secret checks have no unresolved blocking findings
- [ ] Logs, fixtures, screenshots, and bundles contain no secrets or private data
- [ ] Documentation, contracts, task status, and ADRs are synchronized
- [ ] Migration, deployment, rollback, and residual risks are documented

## Residual risks and follow-up

-
