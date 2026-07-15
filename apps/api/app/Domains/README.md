# EduConnect API Domain Architecture

EduConnect is a Laravel modular monolith. Concrete business capabilities own top-level directories under `App\Domains`; they run inside the same application and may share the PostgreSQL database while keeping ownership and dependencies explicit.

## Implemented Domains

| Domain | Current responsibility |
|---|---|
| `Audit` | Append-only evidence for sensitive authorization changes. |
| `Auth` | Registration and authentication application actions already used by the API. |
| `Authorization` | Role/capability catalogs, protected mutations, policy contracts, and admin access decisions. |
| `Courses` | User-owned academic terms, independent course workspaces, archive lifecycle, and onboarding materialization. |
| `Onboarding` | Private academic profile collection, resumable onboarding state, and bounded starter-context drafts. |
| `Planner` | User-owned tasks, scheduled focus sessions, and timezone-safe agenda and weekly projections. |
| `Resources` | Private links, direct object-storage uploads, signed downloads, and recoverable file cleanup. |
| `Users` | The current persisted user model. |

This table records implemented code, not a reservation of future domain names. A phase creates a domain only when it introduces concrete behavior owned by that domain. Do not add empty directories or `.gitkeep` placeholders to anticipate later phases.

## Creation and Placement Rules

- Top-level domain names use PascalCase and the namespace `App\Domains\{Domain}`.
- A domain directory must contain concrete PHP behavior. Add only the subdirectories required by that behavior.
- Suggested future boundaries are directional; choose and document the boundary when its owning phase begins.
- Do not rename or split an existing domain as part of unrelated work. Boundary migrations must preserve behavior and be reviewed deliberately.

When needed, domain subdirectories follow these roles:

- `Actions` contain one application use case or state-changing workflow.
- `Data` or `DTOs` contain typed boundary data; do not create both without a clear distinction.
- `Models` contain domain-owned persistence models and relationships.
- `Policies` contain backend authorization decisions for protected records.
- `Queries` contain reusable, bounded read operations and aggregations.
- `Services` contain cohesive reusable domain behavior that is broader than one action.
- `Jobs` contain queued work that should not run in the request-response path.
- `Enums` contain stable domain statuses and types instead of scattered magic strings.

HTTP controllers, Form Requests, and API Resources remain under `app/Http` unless the project deliberately adopts one consistent alternative. They translate transport concerns and delegate business behavior to the owning domain. Stable cross-cutting technical helpers that do not represent business behavior remain under `app/Support`.

Cross-domain work should call an explicit Action or Service owned by the target domain. Avoid generic repositories, base services, event buses, or other abstractions until repeated production code demonstrates a need.

Each implemented slice must keep validation, authorization, stable API resources, database constraints, and meaningful tests aligned with the behavior it introduces.
