import { SidebarProvider } from '@/components/ui/sidebar';
import { AppSidebar } from '@/components/layout/AppSidebar';
import { AppHeader } from '@/components/layout/AppHeader';
import { TooltipProvider } from '@/components/ui/tooltip';
import { useFlashToast } from '@/hooks/useFlashToast';

export default function AppLayout({ children }) {
    useFlashToast();

    return (
        <SidebarProvider>
            <TooltipProvider>
            <AppSidebar />
            <main className="relative flex min-h-svh w-full flex-1 flex-col bg-muted/30">
                <AppHeader />
                <div className="flex-1 p-4 md:p-6">{children}</div>
            </main>
            </TooltipProvider>
        </SidebarProvider>
    );
}
