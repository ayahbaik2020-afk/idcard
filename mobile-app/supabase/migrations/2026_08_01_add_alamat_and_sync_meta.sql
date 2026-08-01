-- Tambahan setelah schema.sql awal: field alamat dari OCR KTP, dan
-- penanda waktu sinkronisasi terakhir supaya app mobile bisa menampilkan
-- status "data sudah/belum terbaru" sebelum user lanjut registrasi.

alter table staging_contractors add column if not exists alamat text;

create table if not exists sync_meta (
  key text primary key,
  updated_at timestamptz not null default now()
);
alter table sync_meta enable row level security;
create policy "anon read sync_meta" on sync_meta
  for select to anon using (true);
