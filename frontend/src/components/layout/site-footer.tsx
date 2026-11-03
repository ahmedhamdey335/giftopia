import Link from "next/link";
import { Instagram, MapPin } from "lucide-react";
import { Wordmark } from "@/components/brand/wordmark";
import { INSTAGRAM_HANDLE, INSTAGRAM_URL, STUDIO_LOCATION } from "@/content/home";

const links = [
  { href: "/studio", label: "Design studio" },
  { href: "/orders", label: "My orders" },
  { href: "/#faq", label: "FAQ" },
];

export function SiteFooter() {
  return (
    <footer className="mt-16 border-t border-border/70 bg-secondary/40">
      <div className="mx-auto grid max-w-6xl gap-10 px-4 pb-28 pt-12 text-sm sm:px-6 md:grid-cols-[1.4fr_1fr_1fr] md:pb-12">
        <div className="flex flex-col gap-3">
          <Wordmark className="text-3xl" />
          <p className="max-w-xs leading-relaxed text-muted-foreground">
            Handmade resin keepsakes and gifts, poured by hand and designed by you.
          </p>
        </div>

        <nav aria-label="Footer" className="flex flex-col gap-3">
          <h2 className="font-sans text-xs uppercase tracking-[0.2em] text-bronze">Explore</h2>
          {links.map((link) => (
            <Link key={link.href} href={link.href} className="w-fit underline-offset-4 hover:underline">
              {link.label}
            </Link>
          ))}
        </nav>

        <div className="flex flex-col gap-3">
          <h2 className="font-sans text-xs uppercase tracking-[0.2em] text-bronze">Visit & follow</h2>
          <p className="flex items-start gap-2 text-muted-foreground">
            <MapPin aria-hidden className="mt-0.5 size-4 shrink-0 text-gold" />
            {STUDIO_LOCATION}
          </p>
          <a
            href={INSTAGRAM_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="flex w-fit items-center gap-2 underline-offset-4 hover:underline"
          >
            <Instagram aria-hidden className="size-4 text-gold" />
            {INSTAGRAM_HANDLE}
          </a>
        </div>
      </div>
      <div className="border-t border-border/70">
        <p className="mx-auto max-w-6xl px-4 py-4 text-xs text-muted-foreground sm:px-6">
          {"© "}
          Giftopia. Every piece is unique.
        </p>
      </div>
    </footer>
  );
}
