import { NextRequest, NextResponse } from "next/server";
import { supabaseAdmin } from "@/lib/supabase";
import { requireSyncKey } from "@/lib/syncAuth";

type ActiveBan = {
  ktp_no: string;
  contractor_name: string;
  sanction_type: string;
  is_permanent: boolean | number;
  end_date: string | null;
  reason: string | null;
};

type SyncedContractor = {
  id_card: string;
  ktp_no: string;
  name: string;
  company_name: string | null;
  plant_location: string | null;
  status: string | null;
  photo: string | null; // filename only, PHP side sends this; we build a URL
};

type SanctionHistory = {
  id: string;
  ktp_no: string;
  sanction_type: string;
  is_permanent: boolean | number;
  start_date: string | null;
  end_date: string | null;
  revoked_at: string | null;
  reason: string | null;
};

// Called by scripts/sync_from_cloud.php on every run (whether or not there
// was anything to pull) to replace the "synced_*" snapshot tables with the
// current authoritative state from the local MySQL database. This is what
// keeps the mobile apps' blacklist check and QR-scan lookup up to date.
export async function POST(req: NextRequest) {
  const authError = requireSyncKey(req);
  if (authError) return authError;

  let body: {
    active_bans?: ActiveBan[];
    contractors?: SyncedContractor[];
    sanction_history?: SanctionHistory[];
    local_base_url?: string; // e.g. http://192.168.20.17:8081/idcard/public
  };
  try {
    body = await req.json();
  } catch {
    return NextResponse.json({ error: "Invalid JSON body" }, { status: 400 });
  }

  const admin = supabaseAdmin();
  const baseUrl = (body.local_base_url ?? "").replace(/\/$/, "");
  const errors: string[] = [];

  // --- synced_active_bans: full replace ---
  if (body.active_bans) {
    const { error: delErr } = await admin.from("synced_active_bans").delete().neq("ktp_no", "");
    if (delErr) errors.push(`active_bans delete: ${delErr.message}`);
    if (body.active_bans.length > 0) {
      const rows = body.active_bans.map((b) => ({
        ktp_no: b.ktp_no,
        contractor_name: b.contractor_name,
        sanction_type: b.sanction_type,
        is_permanent: !!b.is_permanent,
        end_date: b.end_date,
        reason: b.reason,
        updated_at: new Date().toISOString(),
      }));
      const { error } = await admin.from("synced_active_bans").insert(rows);
      if (error) errors.push(`active_bans insert: ${error.message}`);
    }
  }

  // --- synced_contractors: full replace ---
  if (body.contractors) {
    const { error: delErr } = await admin.from("synced_contractors").delete().neq("id_card", "");
    if (delErr) errors.push(`contractors delete: ${delErr.message}`);
    if (body.contractors.length > 0) {
      const rows = body.contractors.map((c) => ({
        id_card: c.id_card,
        ktp_no: c.ktp_no,
        name: c.name,
        company_name: c.company_name,
        plant_location: c.plant_location,
        status: c.status,
        photo_url: c.photo && baseUrl ? `${baseUrl}/uploads/photos/${c.photo}` : null,
        updated_at: new Date().toISOString(),
      }));
      const { error } = await admin.from("synced_contractors").insert(rows);
      if (error) errors.push(`contractors insert: ${error.message}`);
    }
  }

  // --- synced_sanction_history: full replace ---
  if (body.sanction_history) {
    const { error: delErr } = await admin.from("synced_sanction_history").delete().neq("id", "");
    if (delErr) errors.push(`sanction_history delete: ${delErr.message}`);
    if (body.sanction_history.length > 0) {
      const rows = body.sanction_history.map((s) => ({
        id: String(s.id),
        ktp_no: s.ktp_no,
        sanction_type: s.sanction_type,
        is_permanent: !!s.is_permanent,
        start_date: s.start_date,
        end_date: s.end_date,
        revoked_at: s.revoked_at,
        reason: s.reason,
        updated_at: new Date().toISOString(),
      }));
      const { error } = await admin.from("synced_sanction_history").insert(rows);
      if (error) errors.push(`sanction_history insert: ${error.message}`);
    }
  }

  if (errors.length > 0) {
    return NextResponse.json({ ok: false, errors }, { status: 500 });
  }
  return NextResponse.json({ ok: true });
}
