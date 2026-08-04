"use client";

import Link from "next/link";

/**
 * Back button shown at the top of every screen in the mobile app. Renders
 * as a link when `href` is given (e.g. back to the homepage), otherwise as
 * a button that calls `onClick` (e.g. go back to the previous step of the
 * current flow).
 */
export default function BackButton({
  href,
  onClick,
  label = "Kembali",
}: {
  href?: string;
  onClick?: () => void;
  label?: string;
}) {
  const className =
    "self-start inline-flex items-center gap-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-700 px-3 py-2 text-sm font-medium";
  const content = (
    <>
      <span aria-hidden>{"\u2190"}</span>
      {label}
    </>
  );
  if (onClick) {
    return (
      <button type="button" onClick={onClick} className={className}>
        {content}
      </button>
    );
  }
  return (
    <Link href={href ?? "/"} className={className}>
      {content}
    </Link>
  );
}
