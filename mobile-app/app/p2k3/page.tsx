"use client";

import { useEffect, useRef, useState } from "react";
import { supabase } from "@/lib/supabase";

type ContractorProfile = {
  id_card: string;
  ktp_no: string;
  name: string;
  company_name: string | null;
  plant_location: string | null;
  status: string | null;
  photo_url: string | null;
};

type SanctionHistoryRow = {
  id: string;
  sanction_type: string;
  is_permanent: boolean;
  start_date: string | null;
  end_date: string | null;
  revoked_at: string | null;
  reason: string | null;
};

type Screen = "scan" | "profile" | "new-sanction" | "sanction-sent";

export default function P2K3Page() {
  const [screen, setScreen] = useState<Screen>("scan");
  const [scanError, setScanError] = useState<string | null>(null);
  const [loadingProfile, setLoadingProfile] = useState(false);
  const [profile, setProfile] = useState<ContractorProfile | null>(null);
  const [history, setHistory] = useState<SanctionHistoryRow[]>([]);
  // Bumped every time we need to (re)start the camera while already on the
  // "scan" screen (e.g. after a failed/not-found scan) - `screen` alone
  // doesn't change in that case, so the effect below wouldn't re-run
  // without this, leaving the camera frozen after any failed scan.
  const [scanAttempt, setScanAttempt] = useState(0);
  const scannerDivId = "p2k3-qr-reader";
  const html5QrRef = useRef<import("html5-qrcode").Html5Qrcode | null>(null);
  // Guards against the camera firing the success callback more than once
  // for the same code (fps:10 can decode the same frame a couple of times
  // before React has a chance to swap screens), which previously caused
  // loadProfile() to be kicked off twice and the scanner torn down twice.
  const handledRef = useRef(false);

  useEffect(() => {
    if (screen !== "scan") return;

    let cancelled = false;
    handledRef.current = false;
    setScanError(null);

    import("html5-qrcode").then(({ Html5Qrcode }) => {
      if (cancelled) return;
      const scanner = new Html5Qrcode(scannerDivId);
      html5QrRef.current = scanner;

      scanner
        .start(
          { facingMode: "environment" },
          { fps: 10, qrbox: { width: 240, height: 240 } },
          (decodedText) => {
            if (handledRef.current) return;
            handledRef.current = true;
            // Deliberately NOT calling scanner.stop()/clear() here. The
            // cleanup function below (triggered once `screen` changes away
            // from "scan") is the single place that tears the scanner
            // down - stopping it twice throws ("Cannot stop, scanner is
            // not running or paused"), and calling clear() after React
            // has already removed the container div also throws. Doing
            // it in exactly one place, guarded, avoids both.
            loadProfile(decodedText.trim());
          },
          () => {
            // ignore per-frame "no QR found" noise
          }
        )
        .catch((e) => {
          if (cancelled) return;
          console.error(e);
          setScanError(
            "Tidak bisa mengakses kamera. Pastikan izin kamera diaktifkan."
          );
        });
    });

    return () => {
      cancelled = true;
      const scanner = html5QrRef.current;
      html5QrRef.current = null;
      if (!scanner) return;
      scanner
        .stop()
        .catch(() => {
          // Already stopped/never started - fine, we're tearing down anyway.
        })
        .finally(() => {
          try {
            scanner.clear();
          } catch {
            // clear() is synchronous and throws if the container <div>
            // was already removed from the DOM (e.g. React has already
            // re-rendered to the "profile" screen by the time this runs
            // after a successful scan). Nothing to clean up in that case.
          }
        });
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [screen, scanAttempt]);

  async function loadProfile(idCard: string) {
    setLoadingProfile(true);
    setScanError(null);
    try {
      const { data: contractor, error: cErr } = await supabase
        .from("synced_contractors")
        .select("*")
        .eq("id_card", idCard)
        .maybeSingle();
      if (cErr) throw cErr;
      if (!contractor) {
        setScanError(
          `Kartu "${idCard}" tidak ditemukan di data yang sudah tersinkron. Coba scan ulang setelah sync berikutnya.`
        );
        setScreen("scan");
        setScanAttempt((n) => n + 1);
        return;
      }

      const { data: hist, error: hErr } = await supabase
        .from("synced_sanction_history")
        .select("*")
        .eq("ktp_no", contractor.ktp_no)
        .order("start_date", { ascending: false });
      if (hErr) throw hErr;

      setProfile(contractor as ContractorProfile);
      setHistory((hist as SanctionHistoryRow[]) ?? []);
      setScreen("profile");
    } catch (e) {
      console.error(e);
      setScanError("Gagal memuat data. Periksa koneksi internet.");
      setScreen("scan");
      setScanAttempt((n) => n + 1);
    } finally {
      setLoadingProfile(false);
    }
  }

  return (
    <main className="flex-1 p-5 max-w-sm mx-auto w-full flex flex-col gap-5">
      <h1 className="text-lg font-semibold">Pengawasan P2K3</h1>

      {screen === "scan" && (
        <div className="flex flex-col gap-3">
          <div
            id={scannerDivId}
            className="rounded-xl overflow-hidden border border-slate-700 bg-slate-900 aspect-square"
          />
          {loadingProfile && (
            <p className="text-sm text-slate-400">Memuat profil...</p>
          )}
          {scanError && (
            <div className="flex flex-col gap-2">
              <p className="text-sm text-red-400 bg-red-950/50 border border-red-900 rounded-lg p-3">
                {scanError}
              </p>
              <button
                onClick={() => setScanAttempt((n) => n + 1)}
                className="rounded-xl bg-slate-800 px-6 py-3 font-medium text-sm"
              >
                Coba Scan Lagi
              </button>
            </div>
          )}
          <p className="text-xs text-slate-500">
            Arahkan kamera ke QR code pada kartu ID kontraktor.
          </p>
        </div>
      )}

      {screen === "profile" && profile && (
        <ProfileView
          profile={profile}
          history={history}
          onScanAgain={() => {
            setProfile(null);
            setScreen("scan");
          }}
          onAddSanction={() => setScreen("new-sanction")}
        />
      )}

      {screen === "new-sanction" && profile && (
        <NewSanctionForm
          ktpNo={profile.ktp_no}
          onCancel={() => setScreen("profile")}
          onSent={() => setScreen("sanction-sent")}
        />
      )}

      {screen === "sanction-sent" && (
        <div className="flex flex-col gap-4 items-center text-center py-10">
          <p className="text-lg font-semibold text-green-400">
            Sanksi terkirim
          </p>
          <p className="text-sm text-slate-400">
            Akan tersinkron ke sistem kantor secara otomatis (atau lewat
            tombol Sync Now oleh admin).
          </p>
          <button
            onClick={() => {
              setProfile(null);
              setScreen("scan");
            }}
            className="rounded-xl bg-amber-600 px-6 py-4 font-medium"
          >
            Scan Kartu Lain
          </button>
        </div>
      )}
    </main>
  );
}

function ProfileView({
  profile,
  history,
  onScanAgain,
  onAddSanction,
}: {
  profile: ContractorProfile;
  history: SanctionHistoryRow[];
  onScanAgain: () => void;
  onAddSanction: () => void;
}) {
  const isBanned = (profile.status ?? "").toLowerCase() === "banned";
  return (
    <div className="flex flex-col gap-4">
      <div className="rounded-xl border border-slate-700 bg-slate-900 p-4 flex gap-4 items-center">
        {profile.photo_url ? (
          // eslint-disable-next-line @next/next/no-img-element
          <img
            src={profile.photo_url}
            alt={profile.name}
            className="w-16 h-16 rounded-lg object-cover"
          />
        ) : (
          <div className="w-16 h-16 rounded-lg bg-slate-800" />
        )}
        <div>
          <p className="font-semibold">{profile.name}</p>
          <p className="text-sm text-slate-400">{profile.company_name}</p>
          <p className="text-sm text-slate-400">{profile.plant_location}</p>
          <span
            className={`inline-block mt-1 text-xs rounded-full px-2 py-0.5 ${
              isBanned
                ? "bg-red-900 text-red-300"
                : "bg-green-900 text-green-300"
            }`}
          >
            {profile.status ?? "-"}
          </span>
        </div>
      </div>

      <div>
        <p className="text-sm font-medium mb-2">Histori Sanksi</p>
        {history.length === 0 && (
          <p className="text-sm text-slate-500">
            Tidak ada catatan sanksi.
          </p>
        )}
        <ul className="flex flex-col gap-2">
          {history.map((h) => (
            <li
              key={h.id}
              className="text-sm rounded-lg border border-slate-800 bg-slate-900/60 p-3"
            >
              <div className="flex justify-between">
                <span className="font-medium">
                  {h.sanction_type}
                  {h.is_permanent ? " (permanen)" : ""}
                </span>
                {h.revoked_at ? (
                  <span className="text-xs text-slate-400">Dicabut</span>
                ) : (
                  <span className="text-xs text-amber-400">Berlaku</span>
                )}
              </div>
              <p className="text-slate-400 text-xs mt-1">
                {h.start_date}
                {h.end_date ? ` s/d ${h.end_date}` : ""}
              </p>
              {h.reason && <p className="text-slate-300 mt-1">{h.reason}</p>}
            </li>
          ))}
        </ul>
      </div>

      <div className="flex flex-col gap-2">
        <button
          onClick={onAddSanction}
          className="rounded-xl bg-amber-600 px-6 py-4 font-medium"
        >
          Input Sanksi Baru
        </button>
        <button
          onClick={onScanAgain}
          className="rounded-xl bg-slate-800 px-6 py-4 font-medium"
        >
          Scan Kartu Lain
        </button>
      </div>
    </div>
  );
}

function NewSanctionForm({
  ktpNo,
  onCancel,
  onSent,
}: {
  ktpNo: string;
  onCancel: () => void;
  onSent: () => void;
}) {
  const [type, setType] = useState<"SP1" | "SP2" | "BANNED">("SP1");
  const [permanent, setPermanent] = useState(false);
  const [endDate, setEndDate] = useState("");
  const [reason, setReason] = useState("");
  const [inputBy, setInputBy] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function submit() {
    if (!reason.trim() || !inputBy.trim()) {
      setError("Alasan dan nama petugas P2K3 wajib diisi.");
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      const { error: insertErr } = await supabase
        .from("staging_sanctions")
        .insert({
          ktp_no: ktpNo,
          sanction_type: type,
          is_permanent: type === "BANNED" ? permanent : false,
          end_date: permanent ? null : endDate || null,
          reason,
          input_by: inputBy,
          status: "pending",
        });
      if (insertErr) throw insertErr;
      onSent();
    } catch (e) {
      console.error(e);
      setError("Gagal mengirim sanksi. Periksa koneksi internet.");
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex flex-col gap-4">
      {error && (
        <p className="text-sm text-red-400 bg-red-950/50 border border-red-900 rounded-lg p-3">
          {error}
        </p>
      )}
      <label className="text-sm text-slate-300">
        Jenis Sanksi
        <select
          value={type}
          onChange={(e) => setType(e.target.value as typeof type)}
          className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
        >
          <option value="SP1">SP1</option>
          <option value="SP2">SP2</option>
          <option value="BANNED">BANNED</option>
        </select>
      </label>

      {type === "BANNED" && (
        <label className="flex items-center gap-2 text-sm text-slate-300">
          <input
            type="checkbox"
            checked={permanent}
            onChange={(e) => setPermanent(e.target.checked)}
          />
          Banned permanen
        </label>
      )}

      {!permanent && (
        <label className="text-sm text-slate-300">
          Berlaku sampai (kosongkan jika tidak ada batas)
          <input
            type="date"
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
            className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
          />
        </label>
      )}

      <label className="text-sm text-slate-300">
        Alasan / uraian temuan
        <textarea
          value={reason}
          onChange={(e) => setReason(e.target.value)}
          rows={3}
          className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
        />
      </label>

      <label className="text-sm text-slate-300">
        Nama petugas P2K3
        <input
          value={inputBy}
          onChange={(e) => setInputBy(e.target.value)}
          className="mt-1 w-full rounded-lg bg-slate-900 border border-slate-700 px-3 py-3"
        />
      </label>

      <div className="flex flex-col gap-2">
        <button
          disabled={submitting}
          onClick={submit}
          className="rounded-xl bg-amber-600 disabled:bg-slate-700 px-6 py-4 font-medium"
        >
          {submitting ? "Mengirim..." : "Kirim Sanksi"}
        </button>
        <button
          onClick={onCancel}
          className="rounded-xl bg-slate-800 px-6 py-4 font-medium"
        >
          Batal
        </button>
      </div>
    </div>
  );
}
