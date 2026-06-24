# EduConnect API Domain Architecture

EduConnect is a Laravel modular monolith. Each business capability owns a top-level directory under `App\Domains`; all domains run inside the same API application and share the same PostgreSQL database.

## Domain Ownership

| Domain | Responsibility |
|---|---|
| `Auth` | Authentication workflows and session entry points |
| `Users` | User identity, roles, profiles, and user-level authorization |
| `Onboarding` | Role selection and academic onboarding progression |
| `Dashboard` | Student dashboard aggregation and summary queries |
| `Courses` | Student-owned courses and academic course context |
| `Tasks` | Deadlines, assignments, labs, exams, and task progress |
| `SmartIntake` | Manual sources, uploads, links, and intake processing |
| `Resources` | Curated and user-contributed academic resources |
| `ToolsPrompts` | Tool guides, prompts, and workflow recipes |
| `Templates` | Curated templates and user template instances |
| `Communities` | Communities, memberships, posts, and comments |
| `Mentors` | Mentor profiles, verification state, and help requests |
| `Research` | Research topics, papers, notes, and reading progress |
| `SavedItems` | Cross-domain user bookmarks and collections |
| `Admin` | Protected administration and moderation operations |
| `Analytics` | Product events and bounded operational aggregation |
| `AI` | Provider-neutral AI orchestration, limits, and usage tracking |
| `Notifications` | Queue-backed notification delivery and preferences |

## Placement Rules

Domain code uses the namespace `App\Domains\{Domain}`. Add a subdirectory only when the domain has concrete behavior that belongs there:

- `Actions` contain one application use case or state-changing workflow.
- `Data` or `DTOs` contain typed boundary data; do not create both without a clear distinction.
- `Models` contain domain-owned persistence models and relationships.
- `Policies` contain backend authorization decisions for protected records.
- `Queries` contain reusable, bounded read operations and aggregations.
- `Services` contain cohesive reusable domain behavior that is broader than one action.
- `Jobs` contain queued work that should not run in the request-response path.
- `Enums` contain stable domain statuses and types instead of scattered magic strings.

HTTP controllers, Form Requests, and API Resources remain under `app/Http`. They translate transport concerns and delegate business behavior to the owning domain. Shared technical helpers that do not represent business behavior remain under `app/Support`.

Cross-domain work should call an explicit Action or Service owned by the target domain. Avoid generic repositories, base services, event buses, or other abstractions until repeated production code demonstrates a need.

Each implemented slice must keep validation, authorization, stable API resources, database constraints, and meaningful tests close to the behavior it introduces.
