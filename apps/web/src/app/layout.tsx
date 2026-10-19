import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = {
  title: "BrainSprint — A little space for your mind",
  description:
    "A playful detour through memory, patterns and logic. A little space for a curious mind. An entertainment demo, not a cognitive assessment.",
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
