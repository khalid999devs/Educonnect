"use client";

import {
  Badge,
  Card,
  CardContent,
  CardHeader,
  CardTitle,
  ErrorState,
  Skeleton,
} from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";

import { listRoles } from "@/lib/api/admin-roles";
import { humanizeKey } from "@/lib/format";
import { roleKeys } from "@/lib/query-keys";

export function RolesView() {
  const query = useQuery({ queryKey: roleKeys.list(), queryFn: listRoles });

  return (
    <div className="space-y-6">
      <header className="space-y-1">
        <h1 className="text-h2 text-text-primary">Roles</h1>
        <p className="text-body text-text-secondary">
          The platform&rsquo;s roles and the capabilities each one grants.
          Role-to-capability mappings are managed by a super administrator.
        </p>
      </header>

      {query.isPending ? (
        <div className="grid gap-4 md:grid-cols-2">
          {Array.from({ length: 4 }).map((_, index) => (
            <Skeleton key={index} className="h-40 w-full" />
          ))}
        </div>
      ) : query.isError ? (
        <ErrorState
          title="Could not load roles"
          description="The role list could not be loaded."
          onRetry={() => void query.refetch()}
        />
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {query.data.map((role) => (
            <Card key={role.key}>
              <CardHeader>
                <CardTitle>
                  <span className="flex items-center gap-2">
                    {role.name}
                    {role.is_protected ? (
                      <Badge variant="warning">Protected</Badge>
                    ) : null}
                  </span>
                </CardTitle>
              </CardHeader>
              <CardContent>
                {role.capabilities.length === 0 ? (
                  <p className="text-body text-text-muted">No capabilities.</p>
                ) : (
                  <ul className="flex flex-wrap gap-1.5">
                    {role.capabilities.map((capability) => (
                      <li key={capability}>
                        <Badge>{humanizeKey(capability)}</Badge>
                      </li>
                    ))}
                  </ul>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
