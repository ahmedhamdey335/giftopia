import { policyCopy } from "./policies";

export const INSTAGRAM_URL = "https://instagram.com/giftopia00";
export const INSTAGRAM_HANDLE = "@giftopia00";
export const STUDIO_LOCATION = "Tanta · Kafr El Zayat, Gharbia";

export interface ShowcaseItem {
  title: string;
  description: string;
  image: string;
  alt: string;
}

export const categories: ShowcaseItem[] = [
  {
    title: "Initial necklaces",
    description: "Your letter, filled with real pressed flowers.",
    image: "/images/hero-necklaces.png",
    alt: "Three resin initial pendants with pressed flowers on silver chains",
  },
  {
    title: "Name keychains",
    description: "Arabic or English names in gold or coloured script.",
    image: "/images/name-keychain.png",
    alt: "Lavender resin keychain with an Arabic name and silver flakes",
  },
  {
    title: "Phone cases",
    description: "Ocean waves, sand and tiny starfish you can carry.",
    image: "/images/ocean-phone-case.png",
    alt: "Two resin phone cases with turquoise and pink ocean art",
  },
  {
    title: "Trays & coasters",
    description: "Marbled pours finished with pearls and gold.",
    image: "/images/pearl-tray.png",
    alt: "Peach marbled resin tray with pearl border and gold handles",
  },
  {
    title: "Notebooks",
    description: "Covers with shells, sand and a verse in gold.",
    image: "/images/ocean-notebook.png",
    alt: "Resin notebook cover with ocean art, seashells and gold calligraphy",
  },
  {
    title: "Statement keychains",
    description: "Bold marble tones with hand-lettered gold.",
    image: "/images/black-gold-keychain.png",
    alt: "Black marbled resin keychain with gold Arabic lettering",
  },
];

export const steps = [
  {
    title: "Design it in the Studio",
    description: "Pick the piece, mould, mood, colours and inserts. Add a name or a short message.",
  },
  {
    title: "Get your price instantly",
    description: "Your quote is calculated as you go, with nothing hidden. Pay a deposit to reserve your slot.",
  },
  {
    title: "Approve your proof",
    description: "For personalised pieces, we send a preview of the text first. Nothing is poured until you say yes.",
  },
  {
    title: "Poured, cured, delivered",
    description: "We hand-pour and cure your piece, then deliver it in about 7 days after your deposit.",
  },
];

export const promises = [
  {
    title: "Real flowers, shells & gold",
    description: "Every insert is a real material, placed by hand, layer by layer.",
  },
  {
    title: "A proof before we pour",
    description: "You see your personalisation before production starts.",
  },
  {
    title: "Pay in two steps",
    description: "A deposit to start, and the balance only when your piece is packed.",
  },
];

export const faqs = [
  {
    question: "How long does my order take?",
    answer:
      "Most pieces are ready about 7 days after your deposit is confirmed. Resin needs time to cure properly, so we never rush the pour.",
  },
  {
    question: "Where do you deliver?",
    answer: `We currently deliver across Tanta and Gharbia. Our studio is based in ${STUDIO_LOCATION}.`,
  },
  {
    question: "How does payment work?",
    answer:
      "You pay a deposit at checkout to reserve your production slot. When your piece is packed, we ask for the remaining balance before delivery.",
  },
  {
    question: "Can I check the name before it is made?",
    answer:
      "Yes. For personalised pieces we send you a proof of the text, font and colour. You can approve it or ask for changes from your Orders page.",
  },
  {
    question: "Will it look exactly like the photos?",
    answer: policyCopy.handmadeVariation,
  },
  {
    question: "What if something is wrong with my piece?",
    answer: policyCopy.refundPolicy,
  },
];
