import {
    Sidebar,
    SidebarContent,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from "@/components/ui/sidebar"
import { Home, PhoneCall, ShoppingCart, Truck, PieChart } from "lucide-react"
import { Link } from '@inertiajs/react';

const items = [
    { title: "Tổng quan", url: "/", icon: Home },
    { title: "Telesale", url: "/telesale", icon: PhoneCall },
    { title: "Đơn hàng", url: "/orders", icon: ShoppingCart },
    { title: "Kho & Vận chuyển", url: "/logistics", icon: Truck },
    { title: "Báo cáo", url: "/reports", icon: PieChart },
]

export function AppSidebar() {
    return (
        <Sidebar>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel className="text-sm font-bold text-blue-600 uppercase tracking-wider mb-2 mt-2">
                        Pushsale ERP
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton asChild tooltip={item.title}>
                                        <Link href={item.url}>
                                            <item.icon className="w-5 h-5" />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>
        </Sidebar>
    )
}