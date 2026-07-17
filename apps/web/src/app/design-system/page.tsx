import {
  Alert,
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  EmptyState,
  FormField,
  Input,
  MobileNav,
  MobileNavItem,
  Select,
  Sidebar,
  SidebarItem,
  SidebarSection,
  Skeleton,
  Spinner,
  Textarea,
  ThemeToggle,
} from "@educonnect/ui";
import {
  Brain,
  CalendarDays,
  Inbox,
  LayoutDashboard,
  Search,
} from "lucide-react";
import type { Metadata } from "next";
import type { ReactNode } from "react";

import { CopilotDemo } from "@/components/design-system/copilot-demo";
import { DialogDemo } from "@/components/design-system/dialog-demo";
import { ErrorStateDemo } from "@/components/design-system/error-state-demo";
import { UploadDemo } from "@/components/design-system/upload-demo";

export const metadata: Metadata = {
  title: "Design system",
  description:
    "Documented gallery of EduConnect tokens and shared components across their states.",
};

function Section({
  id,
  title,
  description,
  children,
}: {
  id: string;
  title: string;
  description: string;
  children: ReactNode;
}) {
  return (
    <section id={id} aria-labelledby={`${id}-title`} className="space-y-4">
      <div className="space-y-1">
        <h2 id={`${id}-title`} className="text-h3 text-text-primary">
          {title}
        </h2>
        <p className="max-w-2xl text-body text-text-secondary">{description}</p>
      </div>
      {children}
    </section>
  );
}

const COLOR_TOKENS: Array<{ name: string; className: string }> = [
  { name: "brand-primary", className: "bg-brand-primary" },
  { name: "brand-primary-hover", className: "bg-brand-primary-hover" },
  { name: "brand-focus", className: "bg-brand-focus" },
  { name: "bg-canvas", className: "bg-bg-canvas" },
  { name: "bg-surface", className: "bg-bg-surface" },
  { name: "bg-subtle", className: "bg-bg-subtle" },
  { name: "bg-elevated", className: "bg-bg-elevated" },
  { name: "bg-interactive", className: "bg-bg-interactive" },
  { name: "text-primary", className: "bg-text-primary" },
  { name: "text-secondary", className: "bg-text-secondary" },
  { name: "text-muted", className: "bg-text-muted" },
  { name: "border-default", className: "bg-border-default" },
  { name: "border-subtle", className: "bg-border-subtle" },
  { name: "border-strong", className: "bg-border-strong" },
  { name: "status-success", className: "bg-status-success" },
  { name: "status-warning", className: "bg-status-warning" },
  { name: "status-deadline", className: "bg-status-deadline" },
  { name: "status-error", className: "bg-status-error" },
  { name: "status-info", className: "bg-status-info" },
  { name: "status-ai", className: "bg-status-ai" },
  { name: "status-research", className: "bg-status-research" },
];

const TYPE_SCALE: Array<{ name: string; className: string; sample: string }> = [
  { name: "Display XL 56/64", className: "text-display-xl", sample: "Aa" },
  { name: "Display 48/56", className: "text-display", sample: "Aa" },
  { name: "H1 40/48", className: "text-h1", sample: "Plan the week" },
  { name: "H2 32/40", className: "text-h2", sample: "Plan the week" },
  { name: "H3 24/32", className: "text-h3", sample: "Plan the week" },
  { name: "H4 20/28", className: "text-h4", sample: "Plan the week" },
  {
    name: "Body large 16/26",
    className: "text-body-lg",
    sample: "Capture material, plan work, and keep what you learn.",
  },
  {
    name: "Body 14/22",
    className: "text-body",
    sample: "Capture material, plan work, and keep what you learn.",
  },
  { name: "Label 13/18", className: "text-label", sample: "Course code" },
  { name: "Caption 12/18", className: "text-caption", sample: "Updated today" },
  { name: "Button 14/20", className: "text-button", sample: "Save changes" },
];

export default function DesignSystemPage() {
  return (
    <main className="mx-auto w-full max-w-5xl space-y-14 px-6 py-12">
      <header className="space-y-4">
        <h1 className="text-h1 text-text-primary">EduConnect design system</h1>
        <p className="max-w-2xl text-body-lg text-text-secondary">
          The documented gallery for shared tokens and primitives from
          <code className="mx-1 rounded-sm bg-bg-subtle px-1.5 py-0.5 text-body">
            @educonnect/ui
          </code>
          across dark/light, focus, disabled, loading, error, and success
          states. Use the toggle to QA both themes; resize the viewport for
          responsive behavior.
        </p>
        <ThemeToggle />
      </header>

      <Section
        id="colors"
        title="Color tokens"
        description="Semantic tokens only — components never use raw palette classes. Each swatch reads from the active theme."
      >
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
          {COLOR_TOKENS.map((token) => (
            <li key={token.name} className="space-y-1.5">
              <div
                className={`h-12 rounded-md border border-border-default ${token.className}`}
              />
              <p className="text-caption text-text-muted">{token.name}</p>
            </li>
          ))}
        </ul>
      </Section>

      <Section
        id="typography"
        title="Typography"
        description="Satoshi with Inter fallback, sentence case, tabular numbers for dates and analytics. The approved scale is the only one the theme exposes."
      >
        <ul className="space-y-4">
          {TYPE_SCALE.map((entry) => (
            <li
              key={entry.name}
              className="flex flex-col gap-1 border-b border-border-subtle pb-4 sm:flex-row sm:items-baseline sm:gap-6"
            >
              <span className="w-40 shrink-0 text-caption text-text-muted">
                {entry.name}
              </span>
              <span className={`${entry.className} text-text-primary`}>
                {entry.sample}
              </span>
            </li>
          ))}
        </ul>
        <p className="text-body text-text-secondary">
          Tabular numbers:{" "}
          <span className="tabular-nums">09:41 · 12/24 · 87% · 1,024</span>
        </p>
      </Section>

      <Section
        id="radii"
        title="Radii and glow"
        description="Approved radii are 8, 12, 16, and 24px. Borders over heavy shadow; glow is reserved for active navigation, the primary CTA, AI highlights, and the Copilot."
      >
        <div className="flex flex-wrap items-end gap-4">
          {(
            [
              ["rounded-sm", "8"],
              ["rounded-md", "12"],
              ["rounded-lg", "16"],
              ["rounded-xl", "24"],
            ] as const
          ).map(([className, px]) => (
            <div key={className} className="space-y-1.5 text-center">
              <div
                className={`size-20 border border-border-strong bg-bg-subtle ${className}`}
              />
              <p className="text-caption text-text-muted">{px}px</p>
            </div>
          ))}
          <div className="space-y-1.5 text-center">
            <div className="size-20 rounded-lg bg-bg-surface shadow-glow-sm" />
            <p className="text-caption text-text-muted">glow-sm</p>
          </div>
          <div className="space-y-1.5 text-center">
            <div className="size-20 rounded-lg bg-bg-surface shadow-glow" />
            <p className="text-caption text-text-muted">glow</p>
          </div>
        </div>
      </Section>

      <Section
        id="buttons"
        title="Buttons"
        description="44px default height, 48px for marketing and onboarding, 36px only in dense admin rows. Focus is always visible; loading blocks duplicate submission."
      >
        <div className="space-y-4">
          <div className="flex flex-wrap items-center gap-3">
            <Button glow>Primary CTA</Button>
            <Button>Primary</Button>
            <Button variant="secondary">Secondary</Button>
            <Button variant="ghost">Ghost</Button>
            <Button variant="destructive">Delete</Button>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <Button size="lg">Large 48px</Button>
            <Button size="md">Default 44px</Button>
            <Button size="sm" variant="secondary">
              Dense 36px
            </Button>
          </div>
          <div className="flex flex-wrap items-center gap-3">
            <Button isLoading loadingLabel="Saving">
              Saving
            </Button>
            <Button disabled>Disabled</Button>
            <Button variant="secondary" disabled>
              Disabled secondary
            </Button>
          </div>
        </div>
      </Section>

      <Section
        id="forms"
        title="Forms"
        description="Every field pairs a persistent label with hint and error wiring (aria-describedby, aria-invalid). Backend validation stays authoritative."
      >
        <div className="grid gap-6 md:grid-cols-2">
          <FormField label="Course name" hint="As it appears in your syllabus.">
            {(control) => <Input placeholder="Data Structures" {...control} />}
          </FormField>
          <FormField
            label="University email"
            required
            error="This email is already registered."
          >
            {(control) => (
              <Input
                type="email"
                defaultValue="student@university.edu"
                {...control}
              />
            )}
          </FormField>
          <FormField label="Study stage">
            {(control) => (
              <Select defaultValue="undergraduate" {...control}>
                <option value="undergraduate">Undergraduate</option>
                <option value="graduate">Graduate</option>
                <option value="doctoral">Doctoral</option>
              </Select>
            )}
          </FormField>
          <FormField label="Disabled field">
            {(control) => (
              <Input disabled defaultValue="Locked value" {...control} />
            )}
          </FormField>
          <FormField
            label="Notes"
            hint="Plain text; formatting arrives with the editor."
            className="md:col-span-2"
          >
            {(control) => (
              <Textarea
                placeholder="What should future-you remember?"
                {...control}
              />
            )}
          </FormField>
        </div>
      </Section>

      <Section
        id="cards"
        title="Cards"
        description="16px radius, 24px padding, semantic border. Elevation prefers borders and the elevated background over shadows."
      >
        <div className="grid gap-4 md:grid-cols-2">
          <Card>
            <CardHeader>
              <CardTitle>Standard card</CardTitle>
              <CardDescription>
                The default container for dashboard modules and forms.
              </CardDescription>
            </CardHeader>
            <CardContent>
              Content uses body text on the surface background.
            </CardContent>
            <CardFooter>
              <Button size="sm" variant="secondary">
                Action
              </Button>
            </CardFooter>
          </Card>
          <Card className="bg-bg-elevated">
            <CardHeader>
              <CardTitle>Elevated card</CardTitle>
              <CardDescription>
                Uses bg-elevated for stacked surfaces such as popovers.
              </CardDescription>
            </CardHeader>
            <CardContent>Borders carry the depth, not shadows.</CardContent>
          </Card>
        </div>
      </Section>

      <Section
        id="badges-alerts"
        title="Badges and alerts"
        description="Status is never color-only — the label carries the meaning. Warnings and errors announce as alerts; the rest as status."
      >
        <div className="space-y-4">
          <div className="flex flex-wrap gap-2">
            <Badge>Neutral</Badge>
            <Badge variant="brand">Brand</Badge>
            <Badge variant="success">Completed</Badge>
            <Badge variant="warning">Needs review</Badge>
            <Badge variant="deadline">Due Friday</Badge>
            <Badge variant="error">Failed</Badge>
            <Badge variant="info">Info</Badge>
            <Badge variant="ai">AI generated</Badge>
            <Badge variant="research">Research</Badge>
          </div>
          <div className="grid gap-3 md:grid-cols-2">
            <Alert variant="info" title="Session synced">
              Your planner reflects the latest saved changes.
            </Alert>
            <Alert variant="success" title="Course created">
              Data Structures was added to the current term.
            </Alert>
            <Alert variant="warning" title="Review extraction">
              Two extracted deadlines need your confirmation.
            </Alert>
            <Alert variant="error" title="Upload failed">
              The file could not be stored. Retry when you are back online.
            </Alert>
            <Alert variant="ai" title="AI assistance">
              Generated content is always labeled and editable.
            </Alert>
          </div>
        </div>
      </Section>

      <Section
        id="states"
        title="Loading, empty, and error states"
        description="Every route defines loading, empty, error, and success behavior. Empty states are honest — no fake metrics or placeholder activity."
      >
        <div className="space-y-4">
          <div className="flex flex-wrap items-center gap-6">
            <Spinner size="sm" label="Loading small" />
            <Spinner size="md" label="Loading medium" />
            <Spinner size="lg" label="Loading large" />
          </div>
          <Card>
            <div className="flex items-center gap-4">
              <Skeleton className="size-12 rounded-full" />
              <div className="flex-1 space-y-2">
                <Skeleton className="h-4 w-1/2" />
                <Skeleton className="h-3 w-3/4" />
              </div>
            </div>
          </Card>
          <EmptyState
            icon={Inbox}
            title="No captures yet"
            description="Upload a syllabus or lecture material and Smart Intake will organize it for review."
            action={<Button variant="secondary">Upload material</Button>}
          />
          <ErrorStateDemo />
        </div>
      </Section>

      <Section
        id="navigation"
        title="Navigation"
        description="Sidebar rail (240px) with active glow, and the core bottom navigation used under 768px. Planned destinations are disabled with a Soon marker — never presented as available."
      >
        <div className="grid gap-6 lg:grid-cols-[auto_1fr]">
          <div className="h-105 overflow-hidden rounded-lg border border-border-default">
            <Sidebar label="Navigation demo">
              <SidebarSection title="Overview">
                <SidebarItem
                  label="Dashboard"
                  icon={LayoutDashboard}
                  href="/design-system"
                  isActive
                />
                <SidebarItem label="Smart Intake" icon={Inbox} disabled />
              </SidebarSection>
              <SidebarSection title="Plan">
                <SidebarItem label="Planner" icon={CalendarDays} disabled />
                <SidebarItem label="Second Brain" icon={Brain} disabled />
              </SidebarSection>
            </Sidebar>
          </div>
          <div className="space-y-4">
            <div className="rounded-lg border border-border-default p-4">
              <p className="mb-3 text-caption text-text-muted">
                Bottom navigation preview (fixed to the viewport under 768px)
              </p>
              <MobileNav
                label="Core navigation demo"
                className="static block rounded-md border border-border-default pb-0 md:block"
              >
                <MobileNavItem
                  label="Dashboard"
                  icon={LayoutDashboard}
                  href="/design-system"
                  isActive
                />
                <MobileNavItem label="Intake" icon={Inbox} disabled />
                <MobileNavItem label="Planner" icon={CalendarDays} disabled />
                <MobileNavItem label="Brain" icon={Brain} disabled />
                <MobileNavItem label="Search" icon={Search} disabled />
              </MobileNav>
            </div>
            <p className="text-body text-text-secondary">
              The student shell composes these with a 72px top bar; the admin
              console uses its own denser composition. The two apps never share
              navigation config.
            </p>
          </div>
        </div>
      </Section>

      <Section
        id="upload"
        title="Upload"
        description="Type and size limits, real progress, cancel/retry, and the privacy note are mandatory. This demo simulates a transfer to walk the states."
      >
        <UploadDemo />
      </Section>

      <Section
        id="dialog"
        title="Dialog"
        description="Modal with labelled semantics, focus trap and restore, Escape and backdrop close, and page scroll lock. Used for planner and resource create/edit/confirm flows."
      >
        <DialogDemo />
      </Section>

      <Section
        id="copilot"
        title="Copilot trigger"
        description="56px floating action, lower-right safe area, collapsed by default. Each app shell mounts exactly one; this inline copy only demonstrates the states."
      >
        <CopilotDemo />
      </Section>
    </main>
  );
}
