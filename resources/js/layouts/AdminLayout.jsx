import { SidebarProvider, SidebarTrigger } from "@/components/ui/sidebar"
import { AppSidebar } from "@/components/AppSidebar"

export default function AdminLayout({ children }) {
    return (
        <SidebarProvider>
            <AppSidebar />

            <main className="flex-1 w-full relative bg-zinc-50/50">
                <header className="h-14 border-b border-zinc-200 flex items-center px-4 bg-white sticky top-0 z-10 shadow-sm">
                    <SidebarTrigger />

                    <span className="ml-4 font-medium text-sm text-zinc-600">
                        Xin chào, Quản trị viên
                    </span>
                </header>

                <div className="p-6 min-h-[calc(100vh-3.5rem)]">
                    {children}
                </div>
            </main>
        </SidebarProvider>
    )
}