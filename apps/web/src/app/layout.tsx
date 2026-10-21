import type { Metadata, Viewport } from "next";
import "./globals.css";
import "./v3.css";
import "./v3-commerce.css";

const title = "BrainSprint — A fresh perspective";
const description =
  "A playful demo of memory, number and shape challenges. Explore the journey and an illustrative report, for curiosity and entertainment.";

export const metadata: Metadata = {
  metadataBase: new URL(
    process.env.WEB_ORIGIN || "https://brainsprint-voprosovnety.vercel.app",
  ),
  applicationName: "BrainSprint",
  title,
  description,
  openGraph: {
    type: "website",
    locale: "en_US",
    siteName: "BrainSprint",
    url: "/",
    title,
    description,
  },
  twitter: {
    card: "summary_large_image",
    title,
    description,
    images: [{
      url: "/opengraph-image.png",
      alt: "BrainSprint. Give your curiosity a little challenge. Words, numbers and shapes. An interactive demo with illustrative results for entertainment.",
    }],
  },
};

export const viewport: Viewport = {
  themeColor: "#f5f6f8",
};

export default function RootLayout({
  children,
}: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  );
}
