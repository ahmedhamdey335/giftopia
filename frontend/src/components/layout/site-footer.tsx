import { Wordmark } from "@/components/brand/wordmark";

export function SiteFooter() {
  return (
    <footer className="mt-16 border-t border-border/70">
      <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6">
        <Wordmark className="text-xl" />
        <p>Handmade resin keepsakes, poured by hand. Launch delivery in Tanta.</p>
        <a
          href="https://instagram.com/giftopia00"
          target="_blank"
          rel="noopener noreferrer"
          className="underline-offset-4 hover:underline"
        >
          @giftopia00
        </a>
      </div>
    </footer>
  );
}
