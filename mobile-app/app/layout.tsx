import type { Metadata, Viewport } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "IDCard Mobile — Registrasi & P2K3",
  description: "Registrasi man power kontraktor & pengawasan P2K3",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  maximumScale: 1,
  themeColor: "#0f172a",
};

// Catatan: sengaja TIDAK memakai next/font/google (Geist), karena jaringan
// tempat aplikasi ini dibangun/dijalankan memblokir fonts.googleapis.com,
// sama seperti yang sudah dicatat di project p2k3-keselamatan. Pakai
// system font stack lewat Tailwind (font-sans) supaya build tidak butuh
// internet ke domain tsb.
export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="id" className="h-full antialiased">
      <body className="min-h-full flex flex-col bg-slate-950 text-slate-100 font-sans">
        {children}
      </body>
    </html>
  );
}
