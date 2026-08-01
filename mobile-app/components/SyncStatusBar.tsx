"use client";

import { useSyncStatus } from "@/lib/useSyncStatus";

function formatSyncTime(iso: string | null): string {
  if (!iso) return "belum pernah";
  return new Date(iso).toLocaleString("id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  });
}

/**
 * Shows the freshness of data pulled from Supabase (last time the local
 * idcard server pushed a snapshot) and a manual "Sinkronkan" button.
 * Runs automatically on mount - see lib/useSyncStatus.ts for why this
 * refreshes from Supabase rather than reaching into the plant's LAN.
 */
export default function SyncStatusBar() {
  const { status, lastSyncedAt, error, refresh } = useSyncStatus();

  return (
    <div
      className={`w-full max-w-sm rounded-xl border p-3 text-sm flex items-center justify-between gap-3 ${
        status === "ok"
          ? "border-green-900 bg-green-950/40 text-green-300"
          : status === "error"
          ? "border-red-900 bg-red-950/40 text-red-300"
          : "border-slate-700 bg-slate-900 text-slate-300"
      }`}
    >
      <div>
        {status === "loading" && <span>Menyinkronkan data terbaru...</span>}
        {status === "ok" && (
          <span>Data terbaru ✓ ({formatSyncTime(lastSyncedAt)})</span>
        )}
        {status === "error" && <span>{error}</span>}
        {status === "idle" && <span>Menyiapkan sinkronisasi...</span>}
      </div>
      <button
        onClick={refresh}
        disabled={status === "loading"}
        className="shrink-0 rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium disabled:opacity-50"
      >
        {status === "loading" ? "..." : "Sync Now"}
      </button>
    </div>
  );
}
