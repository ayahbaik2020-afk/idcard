import { NextRequest, NextResponse } from "next/server";
import { supabaseAdmin } from "@/lib/supabase";

/**
 * Checks whether a KTP number (NIK) is already registered - either
 * already synced into the local MySQL system (`synced_contractors`) or
 * sitting in another still-pending mobile submission (`staging_contractors`
 * with status='pending'). Both tables are RLS-locked against anon SELECT
 * (see supabase/schema.sql - anon can only INSERT staging rows), so this
 * check has to happen server-side with the service_role key.
 *
 * Public endpoint (no SYNC_API_KEY) - same trust level as the register
 * page itself, which is meant to be used by anyone with the link.
 */
export async function POST(req: NextRequest) {
  let body: { ktp_no?: string };
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body" }, { status: 400 });
  }

  const ktpNo = (body.ktp_no ?? "").trim();
  if (ktpNo.length !== 16) {
    return NextResponse.json({ error: "ktp_no harus 16 digit" }, { status: 400 });
  }

  const admin = supabaseAdmin();

  const [syncedRes, pendingRes] = await Promise.all([
    admin
      .from("synced_contractors")
      .select("id_card, name")
      .eq("ktp_no", ktpNo)
      .maybeSingle(),
    admin
      .from("staging_contractors")
      .select("id, name, created_at")
      .eq("ktp_no", ktpNo)
      .eq("status", "pending")
      .order("created_at", { ascending: true })
      .limit(1)
      .maybeSingle(),
  ]);

  if (syncedRes.error) {
    return NextResponse.json({ error: syncedRes.error.message }, { status: 500 });
  }
  if (pendingRes.error) {
    return NextResponse.json({ error: pendingRes.error.message }, { status: 500 });
  }

  if (syncedRes.data) {
    return NextResponse.json({
      duplicate: true,
      source: "synced",
      id_card: syncedRes.data.id_card,
      name: syncedRes.data.name,
    });
  }

  if (pendingRes.data) {
    return NextResponse.json({
      duplicate: true,
      source: "pending",
      name: pendingRes.data.name,
      submitted_at: pendingRes.data.created_at,
    });
  }

  return NextResponse.json({ duplicate: false });
}
