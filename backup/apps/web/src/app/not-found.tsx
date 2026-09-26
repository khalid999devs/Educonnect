import { buttonClasses, EmptyState } from "@educonnect/ui";
import { Compass } from "lucide-react";
import Link from "next/link";

export default function NotFound() {
  return (
    <main className="flex min-h-dvh items-center justify-center px-6">
      <EmptyState
        icon={Compass}
        title="Page not found"
        description="The address does not exist or is not available yet."
        action={
          <Link href="/" className={buttonClasses({ variant: "secondary" })}>
            Back to home
          </Link>
        }
        className="w-full max-w-lg border-none bg-transparent"
      />
    </main>
  );
}
