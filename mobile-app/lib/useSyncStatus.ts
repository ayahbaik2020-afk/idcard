"use client";

import { useCallback, useEffect, useState } from "react";
import { supabase, type CompanyCache } from "@/lib/supabase";

export type SyncStatus = "idle" | "loading" | "ok" | "error";

/**
 * Refreshes the client's view of the data already pushed into Supabase by
 * the local idcard server's sync script (scripts/sync_from_cloud.php,
 * triggered by the "Sync Now" button in the local dashboard, or by
 * Task Scheduler when that's working).
 *
 * IMPORTANT: this does NOT reach across the internet into the plant's LAN
 * to trigger the local PHP pull/push - that direction isn't reachable
 * from Vercel by design (see MOBILE_APP_PLAN.md section 2: the local
 * server actively pulls from the cloud, not the other way around). What
 * this hook (and the "Sync Now" button that uses it) actually does is
 * re-query Supabase for whatever the local server last pushed, so a phone
 * that's had the page open for a while (or was opened before this
 * session's last local push) sees fresh data instead of a stale first
 * load. Same pattern already used in app/register/page.tsx's own sync
 * gate - extracted here so the homepage can show the same status.
 */
export function useSyncStatus() {
  const [status, setStatus] = useState<SyncStatus>("idle");
  const [lastSyncedAt, setLastSyncedAt] = useState<string | null>(null);
  const [companies, setCompanies] = useState<string[]>([]);
  const [error, setError] = useState<string | null>(null);

  const refresh = useCallback(async () => {
    setStatus("loading");
    setError(null);
    try {
      const [metaRes, companiesRes] = await Promise.all([
        supabase
          .from("sync_meta")
          .select("updated_at")
          .eq("key", "last_push")
          .maybeSingle(),
        supabase.from("contractor_companies_cache").select("name").order("name"),
      ]);
      if (metaRes.error) throw metaRes.error;
      if (companiesRes.error) throw companiesRes.error;

      setLastSyncedAt(metaRes.data?.updated_at ?? null);
      setCompanies(
        (companiesRes.data as CompanyCache[] | null)?.map((c) => c.name) ?? []
      );
      setStatus("ok");
    } catch (e) {
      console.error(e);
      setStatus("error");
      setError(
        "Gagal menyinkronkan data terbaru. Periksa koneksi internet lalu coba lagi."
      );
    }
  }, []);

  // Run once as soon as the component mounts, i.e. right when the page is
  // first opened - this is the "sync saat awal buka" behavior.
  useEffect(() => {
    refresh();
  }, [refresh]);

  return { status, lastSyncedAt, companies, error, refresh };
}
