import { Collections } from "@/components/home/collections";
import { Faq } from "@/components/home/faq";
import { FinalCta } from "@/components/home/final-cta";
import { Hero } from "@/components/home/hero";
import { HowItWorks } from "@/components/home/how-it-works";
import { MobileCtaBar } from "@/components/home/mobile-cta-bar";
import { Promises } from "@/components/home/promises";

export default function HomePage() {
  return (
    <>
      <Hero />
      <Collections />
      <HowItWorks />
      <Promises />
      <Faq />
      <FinalCta />
      <MobileCtaBar />
    </>
  );
}
