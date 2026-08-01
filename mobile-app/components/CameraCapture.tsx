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

  // How much of the container the dashed guide box insets on each side,
  // as a fraction (must match the `inset-[GUIDE_INSET*100%]` class below).
  // Using a fraction (not a fixed px) keeps the math correct regardless of
  // the device's actual rendered container size.
  const GUIDE_INSET = 0.06;

  function capture() {
    const video = videoRef.current;
    if (!video || !video.videoWidth) return;

    // The video element is displayed with object-fit:cover inside a
    // fixed-aspect container (3:2 for card, 3:4 for portrait), and the
    // dashed guide box is inset GUIDE_INSET from the container's edges.
    // The raw video stream's native resolution/aspect usually differs
    // from the container, so previously the FULL raw frame was captured
    // regardless of what the guide box showed - including background
    // outside the card. Replicate the same "cover" math here so the
    // captured image matches exactly what the user saw inside the guide.
    const containerAspect = aspect === "card" ? 3 / 2 : 3 / 4;
    const videoAspect = video.videoWidth / video.videoHeight;

    let coverWidth: number, coverHeight: number, coverX: number, coverY: number;
    if (videoAspect > containerAspect) {
      // Video is relatively wider than the container -> cover crops the sides.
      coverHeight = video.videoHeight;
      coverWidth = video.videoHeight * containerAspect;
      coverX = (video.videoWidth - coverWidth) / 2;
      coverY = 0;
    } else {
      // Video is relatively taller/narrower -> cover crops top/bottom.
      coverWidth = video.videoWidth;
      coverHeight = video.videoWidth / containerAspect;
      coverX = 0;
      coverY = (video.videoHeight - coverHeight) / 2;
    }

    const sx = coverX + coverWidth * GUIDE_INSET;
    const sy = coverY + coverHeight * GUIDE_INSET;
    const sWidth = coverWidth * (1 - 2 * GUIDE_INSET);
    const sHeight = coverHeight * (1 - 2 * GUIDE_INSET);

    const canvas = document.createElement("canvas");
    canvas.width = Math.round(sWidth);
    canvas.height = Math.round(sHeight);
    const ctx = canvas.getContext("2d");
    if (!ctx) return;
    ctx.drawImage(video, sx, sy, sWidth, sHeight, 0, 0, canvas.width, canvas.height);
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
          <div className="absolute inset-[6%] border-2 border-dashed border-white/70 rounded-lg pointer-events-none" />
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
