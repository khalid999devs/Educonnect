"use client";

import { ErrorState, Skeleton } from "@educonnect/ui";
import { useQuery } from "@tanstack/react-query";
import {
  GraduationCap,
  Library,
  MonitorSmartphone,
  UserRound,
} from "lucide-react";
import { useState } from "react";

import { PageCover } from "@/components/shared/page-cover";
import { SectionTabs, type SectionTab } from "@/components/shared/section-tabs";
import { getSettings } from "@/lib/api/settings";
import { settingsKeys } from "@/lib/query-keys";

import { AccountSection } from "./account-section";
import { AcademicProfileSection } from "./academic-profile-section";
import { CoursePreferencesSection } from "./course-preferences-section";
import { SessionsSection } from "./sessions-section";

type SettingsTab = "account" | "academic" | "courses" | "sessions";

const TABS: readonly SectionTab<SettingsTab>[] = [
  { value: "account", label: "Account", icon: UserRound },
  { value: "academic", label: "Academic profile", icon: GraduationCap },
  { value: "courses", label: "Course preferences", icon: Library },
  { value: "sessions", label: "Sessions", icon: MonitorSmartphone },
];

/**
 * Settings hub. The accent is deliberately neutral (build brief 6.2):
 * settings is chrome, not content.
 *
 * Everything is inline. There are no dialogs anywhere on this surface, and
 * every destructive-shaped action confirms in place.
 */
export function SettingsView() {
  const [tab, setTab] = useState<SettingsTab>("account");

  const settingsQuery = useQuery({
    queryKey: settingsKeys.detail(),
    queryFn: () => getSettings(),
    staleTime: 60_000,
  });

  return (
    <div className="space-y-4">
      <div className="motion-safe:animate-fade-up">
        <PageCover
          photo="/marketing/minimal-desk.jpg"
          title="Your account and preferences"
          subtitle="Account details, your academic profile, the courses everything files into, and the browsers signed in to EduConnect."
          headingLevel={1}
          priority
        />
      </div>

      <div className="motion-safe:animate-fade-up motion-safe:[animation-delay:80ms]">
        <SectionTabs
          tabs={TABS}
          value={tab}
          onChange={setTab}
          label="Settings sections"
          panelId={(value) => `settings-panel-${value}`}
        />
      </div>

      <div
        id={`settings-panel-${tab}`}
        role="tabpanel"
        aria-labelledby={`section-tab-${tab}`}
        tabIndex={-1}
        className="motion-safe:animate-fade-up motion-safe:[animation-delay:160ms]"
      >
        {tab === "courses" ? (
          <CoursePreferencesSection />
        ) : tab === "sessions" ? (
          <SessionsSection />
        ) : settingsQuery.isPending ? (
          <SettingsSkeleton />
        ) : settingsQuery.isError ? (
          <ErrorState
            title="Settings could not be loaded"
            description="Your account details are safe. Try the request again."
            onRetry={() => void settingsQuery.refetch()}
          />
        ) : tab === "account" ? (
          <AccountSection settings={settingsQuery.data} />
        ) : (
          <AcademicProfileSection profile={settingsQuery.data.profile} />
        )}
      </div>
    </div>
  );
}

function SettingsSkeleton() {
  return (
    <div className="space-y-3 rounded-lg border border-border-default bg-bg-surface p-6">
      <Skeleton className="h-6 w-48" />
      <Skeleton className="h-4 w-full max-w-md" />
      <Skeleton className="h-11 w-full" />
      <Skeleton className="h-11 w-full" />
      <Skeleton className="h-11 w-40" />
    </div>
  );
}
