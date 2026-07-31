import { NextRequest, NextResponse } from "next/server";
import { supabaseAdmin } from "@/lib/supabase";
import { requireSyncKey } from "@/lib/syncAuth";

type AckItem = { id: string; status: "synced" | "rejected"; message?: string };

// Called by scripts/sync_from_cloud.php after it has processed the rows
// returned by /api/sync/pull, so Supabase stops returning them next time.
export async function POST(req: NextRequest) {
  const authError = requireSyncKey(req);
  if (authError) return authError;

  let body: { contractors?: AckItem[]; sanctions?: AckItem[] };
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body" }, { status: 400 });
  }

  const admin = supabaseAdmin();
  const results = { contractors: 0, sanctions: 0, errors: [] as string[] };

  for (const item of body.contractors ?? []) {
    const { error } = await admin
      .from("staging_contractors")
      .update({ status: item.status, synced_at: new Date().toISOString() })
      .eq("id", item.id);
    if (error) results.errors.push(`contractor ${item.id}: ${error.message}`);
    else results.contractors++;
  }

  for (const item of body.sanctions ?? []) {
    const { error } = await admin
      .from("staging_sanctions")
      .update({ status: item.status, synced_at: new Date().toISOString() })
      .eq("id", item.id);
    if (error) results.errors.push(`sanction ${item.id}: ${error.message}`);
    else results.sanctions++;
  }

  return NextResponse.json(results);
}
