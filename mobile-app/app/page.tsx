import Link from "next/link";
import SyncStatusBar from "@/components/SyncStatusBar";

export default function Home() {
  return (
    <main className="flex-1 flex flex-col items-center justify-center gap-4 p-6">
      <h1 className="text-xl font-semibold text-center mb-2">
        IDCard Mobile
      </h1>
      <SyncStatusBar />
      <Link
        href="/register"
        className="w-full max-w-sm rounded-xl bg-blue-600 active:bg-blue-700 px-6 py-4 text-center font-medium"
      >
        Registrasi Man Power
      </Link>
      <Link
        href="/p2k3"
        className="w-full max-w-sm rounded-xl bg-amber-600 active:bg-amber-700 px-6 py-4 text-center font-medium"
      >
        Pengawasan P2K3
      </Link>
    </main>
  );
}
