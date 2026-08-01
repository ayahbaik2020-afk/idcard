"use client";

import { useEffect, useRef, useState } from "react";

type Props = {
  onCapture: (file: File) => void;
  frameLabel?: string;
  aspect?: "card" | "portrait";
};

/**
 * Live in-page camera capture (getUserMedia + canvas snapshot), so KTP dan
 * foto wajah punya pengalaman "scan" yang sama seperti scanner QR di
 * halaman P2K3 - bukan sekadar membuka app kamera bawaan HP lewat
 * <input type="file" capture>. Fallback ke input file kalau getUserMedia
 * gagal/ditolak (browser lama, izin kamera ditolak, dst).
 */
export default function CameraCapture({ onCapture, frameLabel, aspect = "card" }: Props) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const [ready, setReady] = useState(false);
  const [unavailable, setUnavailable] = useState(false);

  useEffect(() => {
    let cancelled = false;
    if (!navigator.mediaDevices?.getUserMedia) {
      setUnavailable(true);
      return;
    }
    navigator.mediaDevices
      .getUserMedia({ video: { facingMode: "environment" }, audio: false })
      .then((stream) => {
        if (cancelled) {
          stream.getTracks().forEach((t) => t.stop());
          return;
        }
        streamRef.current = stream;
        if (videoRef.current) videoRef.current.srcObject = stream;
        setReady(true);
      })
      .catch((e) => {
        console.error(e);
        if (!cancelled) setUnavailable(true);
      });

    return () => {
      cancelled = true;
      streamRef.current?.getTracks().forEach((t) => t.stop());
      streamRef.current = null;
    };
  }, []);

  function capture() {
    const video = videoRef.current;
    if (!video || !video.videoWidth) return;
    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    ctx.drawImage(video, 0, 0);
    canvas.toBlob(
      (blob) => {
        if (blob) {
          onCapture(new File([blob], `capture-${Date.now()}.jpg`, { type: "image/jpeg" }));
        }
      },
      "image/jpeg",
      0.9
    );
  }

  if (unavailable) {
    return (
      <label className="flex flex-col gap-2">
        <span className="text-sm text-amber-400">
          Kamera langsung tidak tersedia di browser ini. Pakai kamera bawaan HP:
        </span>
        <input
          type="file"
          accept="image/*"
          capture="environment"
          onChange={(e) => {
            const f = e.target.files?.[0];
            if (f) onCapture(f);
          }}
          className="text-sm"
        />
      </label>
    );
  }

  return (
    <div className="flex flex-col gap-3">
      <div
        className={`relative rounded-xl overflow-hidden border border-slate-700 bg-black ${
          aspect === "card" ? "aspect-[3/2]" : "aspect-[3/4]"
        }`}
      >
        <video
          ref={videoRef}
          autoPlay
          playsInline
          muted
          className="w-full h-full object-cover"
        />
        {!ready && (
          <div className="absolute inset-0 flex items-center justify-center text-sm text-slate-400">
            Membuka kamera...
          </div>
        )}
        {ready && (
          <div className="absolute inset-4 border-2 border-dashed border-white/70 rounded-lg pointer-events-none" />
        )}
      </div>
      {frameLabel && (
        <p className="text-xs text-slate-500 text-center">{frameLabel}</p>
      )}
      <button
        type="button"
        disabled={!ready}
        onClick={capture}
        className="rounded-xl bg-blue-600 disabled:bg-slate-700 px-6 py-4 font-medium"
      >
        Ambil Foto
      </button>
    </div>
  );
}
