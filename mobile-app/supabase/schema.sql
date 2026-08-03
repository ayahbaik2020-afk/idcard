-- Jalankan di Supabase SQL Editor (Project -> SQL Editor -> New query)

create extension if not exists "pgcrypto";

create table if not exists contractor_companies_cache (
  name text primary key,
  synced_at timestamptz default now()
);

create table if not exists staging_contractors (
  id uuid primary key default gen_random_uuid(),
  ktp_no text not null,
  name text not null,
  company_name text not null,
  plant_location text not null,
  alamat text,
  ktp_photo_url text,
  face_photo_url text,
  ocr_raw jsonb,
  submitted_by text,
  status text not null default 'pending',
  synced_at timestamptz,
  created_at timestamptz not null default now()
);
create index if not exists idx_staging_contractors_status on staging_contractors(status);

create table if not exists staging_sanctions (
  id uuid primary key default gen_random_uuid(),
  ktp_no text not null,
  sanction_type text not null,
  is_permanent boolean not null default false,
  end_date date,
  reason text,
  input_by text,
  status text not null default 'pending',
  synced_at timestamptz,
  created_at timestamptz not null default now()
);
create index if not exists idx_staging_sanctions_status on staging_sanctions(status);

create table if not exists synced_active_bans (
  ktp_no text primary key,
  contractor_name text,
  sanction_type text,
  is_permanent boolean,
  end_date date,
  reason text,
  updated_at timestamptz not null default now()
);

-- Direktori kontraktor yang sudah terdaftar di sistem lokal (id_card adalah
-- apa yang di-encode di QR fisik di kartu ID -- lihat generateQrCode() di
-- ContractorService.php). Dipakai oleh fitur scan QR P2K3 untuk look-up
-- profil. Di-refresh penuh oleh script sync setiap kali jalan.
create table if not exists synced_contractors (
  id_card text primary key,
  ktp_no text not null,
  name text not null,
  company_name text,
  plant_location text,
  status text,
  photo_url text,
  expiry_date date,
  updated_at timestamptz not null default now()
);
create index if not exists idx_synced_contractors_ktp on synced_contractors(ktp_no);

-- Seluruh histori sanksi (aktif maupun sudah tidak berlaku/dicabut), untuk
-- ditampilkan di halaman detail P2K3 -- synced_active_bans hanya berisi
-- yang MASIH berlaku.
create table if not exists synced_sanction_history (
  id text primary key,          -- id sanksi dari MySQL lokal, sbg text
  ktp_no text not null,
  sanction_type text not null,
  is_permanent boolean,
  start_date date,
  end_date date,
  revoked_at timestamptz,
  reason text,
  updated_at timestamptz not null default now()
);
create index if not exists idx_synced_sanction_history_ktp on synced_sanction_history(ktp_no);

-- Penanda kapan sinkronisasi terakhir dari server lokal berhasil - dipakai
-- oleh app mobile untuk menampilkan status "data sudah/belum terbaru"
-- sebelum user diizinkan lanjut registrasi (lihat app/register/page.tsx).
create table if not exists sync_meta (
  key text primary key,
  updated_at timestamptz not null default now()
);

-- RLS: browser (anon key) hanya boleh INSERT staging + SELECT data yang
-- sudah di-sync turun dari lokal. Tidak boleh update/delete langsung dari HP.
alter table staging_contractors enable row level security;
alter table staging_sanctions enable row level security;
alter table synced_active_bans enable row level security;
alter table contractor_companies_cache enable row level security;
alter table synced_contractors enable row level security;
alter table synced_sanction_history enable row level security;
alter table sync_meta enable row level security;

create policy "anon insert staging_contractors" on staging_contractors
  for insert to anon with check (true);
create policy "anon insert staging_sanctions" on staging_sanctions
  for insert to anon with check (true);
create policy "anon read synced_active_bans" on synced_active_bans
  for select to anon using (true);
create policy "anon read companies_cache" on contractor_companies_cache
  for select to anon using (true);
create policy "anon read synced_contractors" on synced_contractors
  for select to anon using (true);
create policy "anon read synced_sanction_history" on synced_sanction_history
  for select to anon using (true);
create policy "anon read sync_meta" on sync_meta
  for select to anon using (true);

-- Storage bucket untuk foto KTP & foto wajah (buat lewat dashboard Storage,
-- atau lewat query ini):
insert into storage.buckets (id, name, public)
values ('manpower-photos', 'manpower-photos', true)
on conflict (id) do nothing;

create policy "anon upload manpower-photos" on storage.objects
  for insert to anon with check (bucket_id = 'manpower-photos');
create policy "public read manpower-photos" on storage.objects
  for select using (bucket_id = 'manpower-photos');
