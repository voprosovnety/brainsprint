import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = {
  title: "BrainSprint — A little space for your mind",
  description:
    "A playful visual memory challenge. One quiet minute, one curious mind. An entertainment demo, not a cognitive assessment.",
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
