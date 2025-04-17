import { cn } from '@/lib/utils';
import { Analytics } from '@/components/Analytics';
import { Header } from '@/components/Header';
import { useNavigation, useLoaderData } from 'react-router-dom';
import { LoadingFallback } from '@/components/LoadingFallback';
import '@fontsource/inter';

export default function RootLayout({
  children,
}: {
  children: React.ReactNode
}) {
  const navigation = useNavigation();
  const { loaded } = useLoaderData() as { loaded: boolean };
  const isLoading = navigation.state === "loading";

  if (!loaded) {
    return <LoadingFallback />;
  }

  return (
    <div className={cn("min-h-screen bg-background font-sans antialiased")}>
      <div className="relative flex min-h-screen flex-col">
        <Header />
        <div className="flex-1 container-fluid px-4">
          {isLoading ? <LoadingFallback /> : children}
        </div>
      </div>
      <Analytics />
    </div>
  );
} 