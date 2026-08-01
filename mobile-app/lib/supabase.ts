import { createClient } from "@supabase/supabase-js";

// Client dipakai di browser (anon key, akses terbatas oleh RLS policy).
export const supabase = createClient(
  process.env.NEXT_PUBLIC_SUPABASE_URL!,
  process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!
);

// Client dipakai HANYA di server (API routes) — service role, full access.
// JANGAN pernah import file ini dari komponen client ("use client").
export function supabaseAdmin() {
  return createClient(
    process.env.SUPABASE_URL!,
    process.env.SUPABASE_SERVICE_ROLE_KEY!,
    { auth: { persistSession: false } }
  );
}

export type StagingContractor = {
  id: string;
  ktp_no: string;
  name: string;
  alamat: string | null;
  company_name: string;
  plant_location: string;
  ktp_photo_url: string | null;
  face_photo_url: string | null;
  ocr_raw: Record<string, unknown> | null;
  submitted_by: string | null;
  status: "pending" | "synced" | "rejected";
  created_at: string;
};

export type StagingSanction = {
  id: string;
  ktp_no: string;
  sanction_type: "SP1" | "SP2" | "BANNED";
  is_permanent: boolean;
  end_date: string | null;
  reason: string | null;
  input_by: string | null;
  status: "pending" | "synced" | "rejected";
  created_at: string;
};

export type ActiveBan = {
  ktp_no: string;
  contractor_name: string;
  sanction_type: string;
  is_permanent: boolean;
  end_date: string | null;
  reason: string | null;
  updated_at: string;
};

export type CompanyCache = {
  name: string;
  synced_at: string;
};

export type SyncMeta = {
  key: string;
  updated_at: string;
};
