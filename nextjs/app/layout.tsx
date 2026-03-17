import type { Metadata } from "next";
import "./globals.css";
import { RootLayoutWrapper } from "./RootLayoutWrapper";

export const metadata: Metadata = {
  title: "Zaborprofil — Заборы из профнастила",
  description: "Рассчитайте стоимость забора онлайн. Каталог продуктов, калькулятор, смета.",
};

export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return <RootLayoutWrapper>{children}</RootLayoutWrapper>;
}
