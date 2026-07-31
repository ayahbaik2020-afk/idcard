import { NextRequest, NextResponse } from "next/server";

/**
 * Shared-secret auth for the sync endpoints called by the local PHP script
 * (scripts/sync_from_cloud.php on the idcard server), NOT by the mobile
 * apps themselves. Those use SYNC_API_KEY, a separate secret from the
 * Supabase keys, so the local script never needs to hold the powerful
 * Supabase service_role key.
 */
export function requireSyncKey(req: NextRequest): NextResponse | null {
  const provided = req.headers.get("x-sync-key") ?? "";
  const expected = process.env.SYNC_API_KEY ?? "";

  if (!expected || provided.length !== expected.length || !timingSafeEqual(provided, expected)) {
    return NextResponse.json({ error: "Unauthorized" }, { status: 401 });
  }
  return null;
}

function timingSafeEqual(a: string, b: string): boolean {
  if (a.length !== b.length) return false;
  let mismatch = 0;
  for (let i = 0; i < a.length; i++) {
    mismatch |= a.charCodeAt(i) ^ b.charCodeAt(i);
  }
  return mismatch === 0;
}
