'use client';

import { usePathname } from 'next/navigation';

const PAYLOAD_PREFIXES = ['/admin', '/cms-api', '/graphql', '/graphql-playground'];

export function RootLayoutWrapper({
  children,
}: {
  children: React.ReactNode;
}) {
  const pathname = usePathname() ?? '';

  const isPayloadRoute = PAYLOAD_PREFIXES.some((prefix) =>
    pathname.startsWith(prefix)
  );

  if (isPayloadRoute) {
    return <>{children}</>;
  }

  return (
    <html lang="ru">
      <body className="antialiased">{children}</body>
    </html>
  );
}
