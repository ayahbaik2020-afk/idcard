"use client";

import { useEffect, useState } from "react";
import { supabase, type ActiveBan, type CompanyCache } from "@/lib/supabase";
import { scanKtp, type KtpOcrResult } from "@/lib/ocr";

const PLANTS = [
  "CA PLANT",
  "EDC PLANT",
  "VCM PLANT",
  "PVC PLANT",
  "MEI PLANT",
  "HPI PLANT",
];

const NEW_COMPANY_VALUE = "__new__";

type Step = "company" | "ktp" | "blacklist" | "photo" | "done";
type SyncStatus = "idle" | "loading" | "ok" | "error";

export default function RegisterPage() {
  const [step, setStep] = useState<Step>("company");

  // --- Fitur 1: gate sinkronisasi data sebelum boleh lanjut ---
  const [syncStatus, setSyncStatus] = useState<SyncStatus>("idle");
  const [lastSyncedAt, setLastSyncedAt] = useState<string | null>(null);
  const [syncError, setSyncError] = useState<string | null>(null);

  // --- Fitur 2: dropdown PT ---
  const [companies, setCompanies] = useState<string[]>([]);
  const [companySelect, setCompanySelect] = useState<string>("");
  const [newCompanyName, setNewCompanyName] = useState("");
  const company =
    companySelect === NEW_COMPANY_VALUE ? newCompanyName.trim() : companySelect;

  const [plant, setPlant] = useState(PLANTS[0]);
  const [ktpFile, setKtpFile] = useState<File | null>(null);
  const [ktpPreview, setKtpPreview] = useState<string | null>(null);
  const [ocrProgress, setOcrProgress] = useState(0);
  const [ocrResult, setOcrResult] = useState<KtpOcrResult | null>(null);
  const [nik, setNik] = useState("");
  const [nama, setNama] = useState("");
  // --- Fitur 3: alamat dari OCR, bisa dikoreksi ---
  const [alamat, setAlamat] = useState("");
  const [ban, setBan] = useState<ActiveBan | null>(null);
  const [checking, setChecking] = useState(false);
  const [faceFile, setFaceFile] = useState<File | null>(null);
  const [facePreview, setFacePreview] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function runSync() {
    setSyncStatus("loading");
    setSyncError(null);
    try {
      const [metaRes, companiesRes] = await Promise.all([
        supabase.from("sync_meta").select("updated_at").eq("key", "last_push").maybeSingle(),
        supabase.from("contractor_companies_cache").select("name").order("name"),
      ]);
      if (metaRes.error) throw metaRes.error;
      if (companiesRes.error) throw companiesRes.error;

      setLastSyncedAt(metaRes.data?.updated_at ?? null);
      setCompanies((companiesRes.data as CompanyCache[] | null)?.map((c) => c.name) ?? []);
      setSyncStatus("ok");
    } catch (e) {
      console.error(e);
      setSyncStatus("error");
      setSyncError(
        "Gagal menyinkronkan data terbaru (blacklist & daftar PT). Periksa koneksi internet lalu coba lagi."
      );
    }
  }

  useEffect(() => {
    runSync();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  async function handleKtpSelected(file: File) {
    setKtpFile(file);
    setKtpPreview(URL.createObjectURL(file));
    setOcrProgress(0);
    setOcrResult(null);
    setError(null);
    try {
      const result = await scanKtp(file, setOcrProgress);
      setOcrResult(result);
      setNik(result.nik);
      setNama(result.nama);
      setAlamat(result.alamat);
    } catch (e) {
      setError(
        "OCR gagal membaca KTP. Isi NIK, nama, dan alamat secara manual di bawah."
      );
      console.error(e);
    }
  }

  async function checkBlacklistAndContinue() {
    if (nik.replace(/\D/g, "").length !== 16) {
      setError("NIK harus 16 digit. Periksa kembali hasil pembacaan KTP.");
      return;
    }
    if (!nama.trim()) {
      setError("Nama tidak boleh kosong. Isi manual kalau OCR gagal membaca.");
      return;
    }
    setError(null);
    setChecking(true);
    try {
      const { data, error: qErr } = await supabase
        .from("synced_active_bans")
        .select("*")
        .eq("ktp_no", nik)
        .maybeSingle();
      if (qErr) throw qErr;
      setBan((data as ActiveBan) ?? null);
      setStep(data ? "blacklist" : "photo");
    } catch (e) {
      console.error(e);
      setError(
        "Gagal memeriksa status blacklist. Periksa koneksi internet dan coba lagi."
      );
    } finally {
      setChecking(false);
    }
  }

  async function submitRegistration() {
    if (!ktpFile || !faceFile) return;
    setSubmitting(true);
    setError(null);
    try {
      const stamp = Date.now();
      const ktpPath = `ktp/${nik}-${stamp}.jpg`;
      const facePath = `face/${nik}-${stamp}.jpg`;

      const [ktpUp, faceUp] = await Promise.all([
        supabase.storage.from("manpower-photos").upload(ktpPath, ktpFile),
        supabase.storage.from("manpower-photos").upload(facePath, faceFile),
      ]);
      if (ktpUp.error) throw ktpUp.error;
      if (faceUp.error) throw faceUp.error;

      const ktpUrl = supabase.storage
        .from("manpower-photos")
        .getPublicUrl(ktpPath).data.publicUrl;
      const faceUrl = supabase.storage
        .from("manpower-photos")
        .getPublicUrl(facePath).data.publicUrl;

      const { error: insertErr } = await supabase
        .from("staging_contractors")
        .insert({
          ktp_no: nik,
          name: nama,
          alamat: alamat || null,
          company_name: company,
          plant_location: plant,
          ktp_photo_url: ktpUrl,
          face_photo_url: faceUrl,
          ocr_raw: ocrResult ? { ...ocrResult } : null,
          status: "pending",
        });
      if (insertErr) throw insertErr;

      setStep("done");
    } catch (e) {
      console.error(e);
      setError(
        "Gagal mengirim data registrasi. Periksa koneksi internet dan coba lagi."
      );
    } finally {
      setSubmitting(false);
    }
  }

  function formatSyncTime(iso: string | null): string {
    if (!iso) return "belum pernah";
    return new Date(iso).toLocaleString("id-ID", {
      dateStyle: "medium",
      timeStyle: "short",
    });
  }

  return (
    <main className="flex-1 p-5 max-w-sm mx-auto w-full flex flex-col gap-5">
      <h1 className="text-lg font-semibold">Registrasi Man Power</h1>

      {error && (
        <p className="text-sm text-red-400 bg-red-950/50 border border-red-900 rounded-lg p-3">
          {error}
        </p>
      )}

      {step === "company" && (
        <div className="flex flex-col gap-4">
          {/* --- Fitur 1: status sinkronisasi --- */}
          <div
            className={`rounded-xl border p-3 text-sm flex items-center justify-between gap-3 ${
              syncStatus === "ok"
                ? "border-green-900 bg-green-950/40 text-green-300"
                : syncStatus === "error"
                ? "border-red-900 bg-red-950/40 text-red-300"
                : "border-slate-700 bg-slate-900 text-slate-300"
            }`}
          >
            <div>
              {syncStatus === "loading" && <span>Menyinkronkan data terbaru...</span>}
              {syncStatus === "ok" && (
                <span>Data terbaru ✓ ({formatSyncTime(lastSyncedAt)})</span>
              )}
              {syncStatus === "error" && <span>{syncError}</span>}
              {syncStatus === "idle" && <span>Menyiapkan sinkronisasi...</span>}
            </div>
            <button
              onClick={runSync}
              disabled={syncStatus === "loading"}
              className="shrink-0 rounded-lg bg-slate-800 px-3 py-2 text-xs font-medium disabled:opacity-50"
            >
              {syncStatus === "loading" ? "..." : "Sinkronkan"}
            </button>
          </div>

          {/* --- Fitur 2: dropdown PT --- */}
          <label className="text-sm text-slate-300">
            Nama PT (perusahaan kontraktor)
            <select
              value={companySelect}
              onChange={(e) => setCompanySelect(e.target.value)}
              className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
            >
              <option value="" disabled>
                Pilih PT...
              </option>
              {companies.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
              <option value={NEW_COMPANY_VALUE}>+ PT baru (belum ada di daftar)</option>
            </select>
          </label>
          {companySelect === NEW_COMPANY_VALUE && (
            <input
              value={newCompanyName}
              onChange={(e) => setNewCompanyName(e.target.value)}
              placeholder="Ketik nama PT baru"
              className="w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
            />
          )}

          <label className="text-sm text-slate-300">
            Lokasi Plant
            <select
              value={plant}
              onChange={(e) => setPlant(e.target.value)}
              className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
            >
              {PLANTS.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </select>
          </label>
          <button
            disabled={!company || syncStatus !== "ok"}
            onClick={() => setStep("ktp")}
            className="rounded-xl bg-blue-600 disabled:bg-slate-700 px-6 py-4 font-medium"
          >
            {syncStatus !== "ok"
              ? "Menunggu sinkronisasi..."
              : "Lanjut: Scan KTP"}
          </button>
        </div>
      )}

      {step === "ktp" && (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-slate-400">
            Ambil foto KTP dengan pencahayaan cukup, rata, tidak silau.
          </p>
          <input
            type="file"
            accept="image/*"
            capture="environment"
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) handleKtpSelected(f);
            }}
            className="text-sm"
          />
          {ktpPreview && (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={ktpPreview}
              alt="Preview KTP"
              className="rounded-lg border border-slate-700"
            />
          )}
          {ktpFile && !ocrResult && (
            <p className="text-sm text-slate-400">
              Membaca KTP... {ocrProgress}%
            </p>
          )}

          {ktpFile && (
            <div className="flex flex-col gap-3">
              <label className="text-sm text-slate-300">
                NIK (16 digit) — periksa/koreksi hasil scan
                <input
                  value={nik}
                  onChange={(e) => setNik(e.target.value)}
                  inputMode="numeric"
                  maxLength={16}
                  className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3 font-mono"
                />
              </label>
              <label className="text-sm text-slate-300">
                Nama lengkap — periksa/koreksi hasil scan
                <input
                  value={nama}
                  onChange={(e) => setNama(e.target.value)}
                  className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
                />
              </label>
              <label className="text-sm text-slate-300">
                Alamat — periksa/koreksi hasil scan
                <textarea
                  value={alamat}
                  onChange={(e) => setAlamat(e.target.value)}
                  rows={2}
                  className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
                />
              </label>
              <button
                disabled={checking}
                onClick={checkBlacklistAndContinue}
                className="rounded-xl bg-blue-600 disabled:bg-slate-700 px-6 py-4 font-medium"
              >
                {checking ? "Memeriksa..." : "Cek Status & Lanjut"}
              </button>
            </div>
          )}
        </div>
      )}

      {step === "blacklist" && ban && (
        <div className="flex flex-col gap-4">
          <div className="rounded-xl border border-red-800 bg-red-950/40 p-4">
            <p className="font-semibold text-red-300 mb-2">
              NIK ini masuk daftar sanksi aktif
            </p>
            <dl className="text-sm space-y-1 text-slate-200">
              <div>
                <dt className="inline text-slate-400">Nama tercatat: </dt>
                <dd className="inline">{ban.contractor_name}</dd>
              </div>
              <div>
                <dt className="inline text-slate-400">Jenis sanksi: </dt>
                <dd className="inline">
                  {ban.sanction_type}
                  {ban.is_permanent ? " (PERMANEN)" : ""}
                </dd>
              </div>
              {!ban.is_permanent && ban.end_date && (
                <div>
                  <dt className="inline text-slate-400">Berlaku sampai: </dt>
                  <dd className="inline">{ban.end_date}</dd>
                </div>
              )}
              <div>
                <dt className="inline text-slate-400">Alasan: </dt>
                <dd className="inline">{ban.reason || "-"}</dd>
              </div>
            </dl>
          </div>
          <p className="text-sm text-slate-400">
            Registrasi dari HP dihentikan untuk NIK ini. Hubungi admin/P2K3
            di kantor bila status ini perlu ditinjau ulang.
          </p>
          <button
            onClick={() => setStep("company")}
            className="rounded-xl bg-slate-800 px-6 py-4 font-medium"
          >
            Registrasi Orang Lain
          </button>
        </div>
      )}

      {step === "photo" && (
        <div className="flex flex-col gap-4">
          <p className="text-sm text-slate-400">
            NIK bersih dari sanksi aktif. Lanjut ambil foto wajah/orangnya.
          </p>
          <input
            type="file"
            accept="image/*"
            capture="user"
            onChange={(e) => {
              const f = e.target.files?.[0];
              if (f) {
                setFaceFile(f);
                setFacePreview(URL.createObjectURL(f));
              }
            }}
            className="text-sm"
          />
          {facePreview && (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={facePreview}
              alt="Preview foto"
              className="rounded-lg border border-slate-700"
            />
          )}
          <button
            disabled={!faceFile || submitting}
            onClick={submitRegistration}
            className="rounded-xl bg-green-600 disabled:bg-slate-700 px-6 py-4 font-medium"
          >
            {submitting ? "Mengirim..." : "Kirim Registrasi"}
          </button>
        </div>
      )}

      {step === "done" && (
        <div className="flex flex-col gap-4 items-center text-center py-10">
          <p className="text-lg font-semibold text-green-400">
            Registrasi terkirim
          </p>
          <p className="text-sm text-slate-400">
            Data akan tersinkron ke sistem kantor secara otomatis (atau lewat
            tombol Sync Now oleh admin).
          </p>
          <button
            onClick={() => window.location.reload()}
            className="rounded-xl bg-blue-600 px-6 py-4 font-medium"
          >
            Registrasi Orang Baru
          </button>
        </div>
      )}
    </main>
  );
}
