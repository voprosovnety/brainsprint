import type { Metadata } from "next";
import "./globals.css";
import "./v3.css";
import "./v3-commerce.css";
export const metadata: Metadata = {
  title: "BrainSprint — A fresh perspective",
  description:
    "A journey through words, numbers and shapes. Explore your curiosity and a fresh perspective on everyday thinking.",
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
