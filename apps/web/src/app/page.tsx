import { buttonClasses, EduConnectThemedLogo } from "@educonnect/ui";
import Link from "next/link";

export default function LandingPlaceholderPage() {
  return (
    <main className="flex min-h-dvh flex-col items-center justify-center gap-6 px-6 text-center">
      <EduConnectThemedLogo width={220} priority />
      <h1 className="max-w-xl text-h3 text-text-primary">
        The academic workspace for university students
      </h1>
      <p className="max-w-md text-body-lg text-text-secondary">
        The marketing site and Live Demo arrive with Phase 19. The application
        shell and shared design system are available now.
      </p>
      <div className="flex flex-wrap items-center justify-center gap-3">
        <Link href="/dashboard" className={buttonClasses({ glow: true })}>
          Open the app shell
        </Link>
        <Link
          href="/design-system"
          className={buttonClasses({ variant: "secondary" })}
        >
          View the design system
        </Link>
      </div>
    </main>
  );
}
