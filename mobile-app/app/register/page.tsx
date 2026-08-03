"use client";

import { useState } from "react";
import { supabase, type ActiveBan } from "@/lib/supabase";
import { scanKtp, isPlausibleNik, type KtpOcrResult } from "@/lib/ocr";
import { useSyncStatus } from "@/lib/useSyncStatus";
import CameraCapture from "@/components/CameraCapture";

const PLANTS = [
  "CA PLANT",
  "EDC PLANT",
  "VCM PLANT",
  "PVC PLANT",
  "MEI PLANT",
  "HPI PLANT",
];

const NEW_COMPANY_VALUE = "__new__";

type Step = "company" | "ktp" | "duplicate" | "reactivate" | "blacklist" | "photo" | "done";

type DuplicateInfo = {
  source: "synced" | "pending";
  name?: string;
  id_card?: string;
  submitted_at?: string;
  expired?: boolean;
  expiry_date?: string | null;
};

/** Per-field read-quality status shown to the user right after OCR, so a
 * silently-empty or structurally-implausible field is visible immediately
 * instead of just looking like an ordinary blank input. This does NOT
 * confirm a field is correct - only that it's worth (or not worth) a
 * second look before continuing. */
type FieldStatus = "ok" | "warn" | "empty";

function nikFieldStatus(value: string): FieldStatus {
  const digits = value.replace(/\D/g, "");
  if (!digits) return "empty";
  if (digits.length !== 16) return "warn";
  return isPlausibleNik(digits) ? "ok" : "warn";
}

function textFieldStatus(value: string, minLen: number): FieldStatus {
  const trimmed = value.trim();
  if (!trimmed) return "empty";
  return trimmed.length < minLen ? "warn" : "ok";
}

const FIELD_STATUS_STYLE: Record<
  FieldStatus,
  { icon: string; label: string; className: string }
> = {
  ok: {
    icon: "\u2713",
    label: "Terbaca",
    className: "border-green-900 bg-green-950/30 text-green-300",
  },
  warn: {
    icon: "\u26A0",
    label: "Perlu dicek",
    className: "border-amber-900 bg-amber-950/30 text-amber-300",
  },
  empty: {
    icon: "\u2717",
    label: "Tidak terbaca",
    className: "border-red-900 bg-red-950/30 text-red-300",
  },
};

function FieldStatusRow({
  label,
  value,
  status,
}: {
  label: string;
  value: string;
  status: FieldStatus;
}) {
  const s = FIELD_STATUS_STYLE[status];
  return (
    <div
      className={`flex items-center justify-between gap-2 rounded-lg border px-3 py-2 text-xs ${s.className}`}
    >
      <div className="flex flex-col min-w-0">
        <span className="opacity-70">{label}</span>
        <span className="truncate font-mono">{value || "(kosong)"}</span>
      </div>
      <span className="shrink-0 font-medium whitespace-nowrap">
        {s.icon} {s.label}
      </span>
    </div>
  );
}

export default function RegisterPage() {
  const [step, setStep] = useState<Step>("company");

  // --- Fitur 1: gate sinkronisasi data sebelum boleh lanjut ---
  // Shared with the homepage's SyncStatusBar - see lib/useSyncStatus.ts.
  const { status: syncStatus, lastSyncedAt, companies, error: syncError, refresh: runSync } = useSyncStatus();

  // --- Fitur 2: dropdown PT ---
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
  const [dup, setDup] = useState<DuplicateInfo | null>(null);
  const [checking, setChecking] = useState(false);
  const [faceFile, setFaceFile] = useState<File | null>(null);
  const [facePreview, setFacePreview] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

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

  async function checkDuplicateBlacklistAndContinue() {
    const cleanNik = nik.replace(/\D/g, "");
    if (cleanNik.length !== 16) {
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
      // Proteksi NIK/KTP double: cek dulu apakah NIK ini sudah terdaftar
      // (baik yang sudah tersinkron ke sistem lokal, maupun submission
      // lain yang masih pending) sebelum boleh lanjut ambil foto & kirim.
      const dupRes = await fetch("/api/register/check-ktp", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ ktp_no: cleanNik }),
      });
      const dupData = await dupRes.json();
      if (!dupRes.ok) throw new Error(dupData.error || "Gagal cek duplikat NIK");

      if (dupData.duplicate) {
        const expired =
          dupData.source === "synced" && dupData.expired === true;
        setDup({
          source: dupData.source,
          name: dupData.name,
          id_card: dupData.id_card,
          submitted_at: dupData.submitted_at,
          expired,
          expiry_date: dupData.expiry_date ?? null,
        });
        // NIK yang kartunya sudah expired boleh di-reaktivasi (ID Card
        // baru). NIK yang masih aktif langsung diblokir.
        setStep(expired ? "reactivate" : "duplicate");
        return;
      }

      await checkBlacklistAndContinue(cleanNik);
    } catch (e) {
      console.error(e);
      setError(
        "Gagal memeriksa status NIK. Periksa koneksi internet dan coba lagi."
      );
    } finally {
      setChecking(false);
    }
  }

  async function checkBlacklistAndContinue(cleanNik: string) {
    const { data, error: qErr } = await supabase
      .from("synced_active_bans")
      .select("*")
      .eq("ktp_no", cleanNik)
      .maybeSingle();
    if (qErr) throw qErr;
    setBan((data as ActiveBan) ?? null);
    setStep(data ? "blacklist" : "photo");
  }

  async function proceedWithReactivation() {
    const cleanNik = nik.replace(/\D/g, "");
    setError(null);
    setChecking(true);
    try {
      await checkBlacklistAndContinue(cleanNik);
    } catch (e) {
      console.error(e);
      setError(
        "Gagal memeriksa status NIK. Periksa koneksi internet dan coba lagi."
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
          {!ktpFile && (
            <>
              <p className="text-sm text-slate-400">
                Posisikan KTP di dalam kotak, pencahayaan cukup &amp; rata,
                tidak silau, lalu tekan Ambil Foto.
              </p>
              <CameraCapture
                aspect="card"
                frameLabel="Posisikan KTP rata di dalam kotak"
                onCapture={handleKtpSelected}
              />
            </>
          )}
          {ktpPreview && (
            <div className="flex flex-col gap-2">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={ktpPreview}
                alt="Preview KTP"
                className="rounded-lg border border-slate-700"
              />
              <button
                type="button"
                onClick={() => {
                  setKtpFile(null);
                  setKtpPreview(null);
                  setOcrResult(null);
                }}
                className="self-start text-xs text-slate-400 underline"
              >
                Ambil ulang foto KTP
              </button>
            </div>
          )}
          {ktpFile && !ocrResult && (
            <div className="flex flex-col gap-1.5">
              <p className="text-sm text-slate-400">
                Membaca KTP (NIK, Nama, Alamat)... {ocrProgress}%
              </p>
              <div className="h-1.5 w-full rounded-full bg-slate-800 overflow-hidden">
                <div
                  className="h-full rounded-full bg-blue-600 transition-all duration-200"
                  style={{ width: `${ocrProgress}%` }}
                />
              </div>
            </div>
          )}

          {ktpFile && (
            <div className="flex flex-col gap-3">
              {ocrResult && (
                <div className="flex flex-col gap-2">
                  <p className="text-xs text-slate-400">
                    Hasil pemindaian sistem — cocokkan dengan KTP asli
                    sebelum lanjut. Status ini ikut update saat kamu koreksi
                    field di bawah.
                  </p>
                  <FieldStatusRow
                    label="NIK"
                    value={nik}
                    status={nikFieldStatus(nik)}
                  />
                  <FieldStatusRow
                    label="Nama"
                    value={nama}
                    status={textFieldStatus(nama, 3)}
                  />
                  <FieldStatusRow
                    label="Alamat"
                    value={alamat}
                    status={textFieldStatus(alamat, 8)}
                  />
                </div>
              )}
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
              {ocrResult?.rawText && (
                <details className="rounded-lg border border-slate-800 bg-slate-950/50 text-xs">
                  <summary className="cursor-pointer select-none px-3 py-2 text-slate-400">
                    Lihat teks mentah hasil OCR (untuk debug bila masih salah baca)
                  </summary>
                  <pre className="whitespace-pre-wrap break-words px-3 pb-3 text-slate-500 font-mono">
                    {ocrResult.rawText}
                  </pre>
                </details>
              )}
              <button
                disabled={checking}
                onClick={checkDuplicateBlacklistAndContinue}
                className="rounded-xl bg-blue-600 disabled:bg-slate-700 px-6 py-4 font-medium"
              >
                {checking ? "Memeriksa..." : "Cek Status & Lanjut"}
              </button>
            </div>
          )}
        </div>
      )}

      {step === "duplicate" && dup && (
        <div className="flex flex-col gap-4">
          <div className="rounded-xl border border-amber-800 bg-amber-950/40 p-4">
            <p className="font-semibold text-amber-300 mb-2">
              NIK ini sudah terdaftar
            </p>
            <dl className="text-sm space-y-1 text-slate-200">
              {dup.name && (
                <div>
                  <dt className="inline text-slate-400">Nama tercatat: </dt>
                  <dd className="inline">{dup.name}</dd>
                </div>
              )}
              {dup.source === "synced" && dup.id_card && (
                <div>
                  <dt className="inline text-slate-400">ID Card: </dt>
                  <dd className="inline">{dup.id_card}</dd>
                </div>
              )}
              {dup.source === "pending" && (
                <div className="text-slate-400">
                  Ada pengajuan lain untuk NIK ini yang masih menunggu
                  disinkronkan ke sistem kantor — belum resmi terdaftar,
                  tapi jangan didaftarkan dua kali.
                </div>
              )}
            </dl>
          </div>
          <p className="text-sm text-slate-400">
            Kalau ini memang orang yang sama, tidak perlu didaftarkan
            ulang. Kalau menurutmu ini keliru (NIK typo saat OCR, dsb),
            koreksi NIK di langkah sebelumnya lalu cek ulang.
          </p>
          <div className="flex gap-2">
            <button
              onClick={() => setStep("ktp")}
              className="flex-1 rounded-xl bg-slate-800 px-6 py-4 font-medium"
            >
              Koreksi NIK
            </button>
            <button
              onClick={() => window.location.reload()}
              className="flex-1 rounded-xl bg-slate-800 px-6 py-4 font-medium"
            >
              Registrasi Orang Lain
            </button>
          </div>
        </div>
      )}

      {step === "reactivate" && dup && (
        <div className="flex flex-col gap-4">
          <div className="rounded-xl border border-amber-800 bg-amber-950/40 p-4">
            <p className="font-semibold text-amber-300 mb-2">
              ID Card sudah tidak berlaku
            </p>
            <dl className="text-sm space-y-1 text-slate-200">
              {dup.name && (
                <div>
                  <dt className="inline text-slate-400">Nama tercatat: </dt>
                  <dd className="inline">{dup.name}</dd>
                </div>
              )}
              {dup.id_card && (
                <div>
                  <dt className="inline text-slate-400">ID Card lama: </dt>
                  <dd className="inline">{dup.id_card}</dd>
                </div>
              )}
              {dup.expiry_date && (
                <div>
                  <dt className="inline text-slate-400">Berakhir: </dt>
                  <dd className="inline">{dup.expiry_date}</dd>
                </div>
              )}
            </dl>
          </div>
          <p className="text-sm text-slate-400">
            NIK ini sudah terdaftar, tapi ID Card-nya sudah melewati masa
            berlaku. Re Aktivasi akan menerbitkan ID Card baru dengan nomor
            baru. Masa berlaku kartu baru diatur admin setelah data
            tersinkron ke sistem kantor.
          </p>
          <p className="text-sm font-medium text-slate-300">
            Apakah anda akan melakukan Re Aktivasi ID dengan yang baru?
          </p>
          <div className="flex gap-2">
            <button
              disabled={checking}
              onClick={() => setStep("ktp")}
              className="flex-1 rounded-xl bg-slate-800 px-6 py-4 font-medium"
            >
              Koreksi NIK
            </button>
            <button
              disabled={checking}
              onClick={proceedWithReactivation}
              className="flex-1 rounded-xl bg-blue-600 px-6 py-4 font-medium"
            >
              {checking ? "Memeriksa..." : "Re Aktivasi ID"}
            </button>
          </div>
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
          {!faceFile && (
            <CameraCapture
              aspect="portrait"
              frameLabel="Posisikan wajah di tengah kotak"
              onCapture={(f) => {
                setFaceFile(f);
                setFacePreview(URL.createObjectURL(f));
              }}
            />
          )}
          {facePreview && (
            <div className="flex flex-col gap-2">
              {/* eslint-disable-next-line @next/next/no-img-element */}
              <img
                src={facePreview}
                alt="Preview foto"
                className="rounded-lg border border-slate-700"
              />
              <button
                type="button"
                onClick={() => {
                  setFaceFile(null);
                  setFacePreview(null);
                }}
                className="self-start text-xs text-slate-400 underline"
              >
                Ambil ulang foto
              </button>
            </div>
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
