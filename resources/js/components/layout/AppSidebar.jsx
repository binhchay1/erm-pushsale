import { Link, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Home,
    Megaphone,
    Package,
    PhoneCall,
    Plug,
    RefreshCw,
    Settings,
    ShoppingCart,
    Truck,
    Wallet,
    AlertTriangle,
} from 'lucide-react';

import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';

const adminGroups = [
    {
        label: 'Tổng quan',
        items: [
            { title: 'Dashboard CEO', url: '/admin/dashboard', icon: Home },
            { title: 'Báo cáo CEO', url: '/admin/reports/ceo', icon: BarChart3 },
        ],
    },
    {
        label: 'Marketing',
        items: [
            { title: 'Dashboard MKT', url: '/admin/marketing/dashboard', icon: Megaphone },
            { title: 'BC doanh số MKT', url: '/admin/marketing/revenue', icon: BarChart3 },
        ],
    },
    {
        label: 'Sale & KH',
        items: [
            { title: 'BC doanh số Sale', url: '/admin/sales/revenue', icon: PhoneCall },
        ],
    },
    {
        label: 'Hệ thống',
        items: [
            { title: 'Tích hợp nền tảng', url: '/admin/integrations', icon: Plug },
            { title: 'Nhật ký lead', url: '/admin/leads', icon: RefreshCw },
        ],
    },
    {
        label: 'Vận hành',
        items: [
            { title: 'Kế toán', url: '/admin/accounting', icon: Wallet },
            { title: 'Thủ kho', url: '/admin/warehouse/operations', icon: Truck },
            { title: 'Tồn kho SP', url: '/admin/warehouse/inventory', icon: Package },
            { title: 'Đơn lỗi', url: '/admin/orders/failed', icon: AlertTriangle },
        ],
    },
];

const salesItems = [
    { title: 'Tác nghiệp', url: '/sales/workspace', icon: PhoneCall },
    { title: 'Hồ sơ KH', url: '/sales/customers', icon: Home },
    { title: 'Cài đặt', url: '/settings', icon: Settings },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const isAdmin = auth.user?.role === 'admin';

    return (
        <Sidebar>
            <SidebarContent>
                <SidebarGroup>
                    <SidebarGroupLabel className="text-xs font-bold uppercase tracking-wider text-primary">
                        ERM SaleOps
                    </SidebarGroupLabel>
                </SidebarGroup>

                {isAdmin ? (
                    adminGroups.map((group) => (
                        <SidebarGroup key={group.label}>
                            <SidebarGroupLabel className="text-xs text-muted-foreground">
                                {group.label}
                            </SidebarGroupLabel>
                            <SidebarGroupContent>
                                <SidebarMenu>
                                    {group.items.map((item) => (
                                        <SidebarMenuItem key={item.url}>
                                            <SidebarMenuButton asChild tooltip={item.title}>
                                                <Link href={item.url}>
                                                    <item.icon className="size-4" />
                                                    <span>{item.title}</span>
                                                </Link>
                                            </SidebarMenuButton>
                                        </SidebarMenuItem>
                                    ))}
                                </SidebarMenu>
                            </SidebarGroupContent>
                        </SidebarGroup>
                    ))
                ) : (
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                {salesItems.map((item) => (
                                    <SidebarMenuItem key={item.url}>
                                        <SidebarMenuButton asChild tooltip={item.title}>
                                            <Link href={item.url}>
                                                <item.icon className="size-4" />
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuButton>
                                    </SidebarMenuItem>
                                ))}
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                )}

                {isAdmin && (
                    <SidebarGroup>
                        <SidebarGroupContent>
                            <SidebarMenu>
                                <SidebarMenuItem>
                                    <SidebarMenuButton asChild>
                                        <Link href="/settings">
                                            <Settings className="size-4" />
                                            <span>Cài đặt</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            </SidebarMenu>
                        </SidebarGroupContent>
                    </SidebarGroup>
                )}
            </SidebarContent>
            <SidebarFooter className="px-3 py-2 text-xs text-muted-foreground">
                {isAdmin ? 'Quản trị hệ thống' : 'Telesale'}
            </SidebarFooter>
        </Sidebar>
    );
}
