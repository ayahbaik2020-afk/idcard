import { NextRequest, NextResponse } from "next/server";
import { supabaseAdmin } from "@/lib/supabase";
import { requireSyncKey } from "@/lib/syncAuth";

// Called by scripts/sync_from_cloud.php on the local idcard server.
// Returns every staging_contractors / staging_sanctions row still
// pending, so the PHP script can insert them into MySQL.
export async function POST(req: NextRequest) {
  const authError = requireSyncKey(req);
  if (authError) return authError;

  const admin = supabaseAdmin();

  const [{ data: contractors, error: cErr }, { data: sanctions, error: sErr }] =
    await Promise.all([
      admin
        .from("staging_contractors")
        .select("*")
        .eq("status", "pending")
        .order("created_at", { ascending: true }),
      admin
        .from("staging_sanctions")
        .select("*")
        .eq("status", "pending")
        .order("created_at", { ascending: true }),
    ]);

  if (cErr || sErr) {
    return NextResponse.json(
      { error: (cErr ?? sErr)?.message ?? "Query failed" },
      { status: 500 }
    );
  }

  return NextResponse.json({
    contractors: contractors ?? [],
    sanctions: sanctions ?? [],
  });
}
