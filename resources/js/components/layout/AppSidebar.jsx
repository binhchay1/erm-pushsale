import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    BarChart3,
    ClipboardList,
    FileCheck,
    Home,
    LineChart,
    Megaphone,
    Package,
    PhoneCall,
    Plug,
    RefreshCw,
    Settings,
    Target,
    Trophy,
    Truck,
    Wallet,
    Warehouse,
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
        label: 'Điều hành',
        items: [
            { title: 'Dashboard CEO', url: '/admin/dashboard', icon: Home },
            { title: 'Tổng hợp vận hành', url: '/admin/reports/business', icon: Activity },
            { title: 'Báo cáo CEO', url: '/admin/reports/ceo', icon: BarChart3 },
            { title: 'Xếp hạng doanh thu', url: '/admin/rankings', icon: Trophy },
        ],
    },
    {
        label: 'Marketing',
        items: [
            { title: 'Dashboard MKT', url: '/admin/marketing/dashboard', icon: Megaphone },
            { title: 'Chiến dịch MKT', url: '/admin/marketing/campaigns', icon: Target },
            { title: 'BC doanh số MKT', url: '/admin/marketing/revenue', icon: LineChart },
        ],
    },
    {
        label: 'Telesale',
        items: [
            { title: 'BC doanh số Sale', url: '/admin/sales/revenue', icon: PhoneCall },
            { title: 'Nhật ký lead', url: '/admin/leads', icon: RefreshCw },
        ],
    },
    {
        label: 'Hệ thống',
        items: [
            { title: 'Tích hợp nền tảng', url: '/admin/integrations', icon: Plug },
            { title: 'API vận chuyển', url: '/admin/shipping-partners', icon: Truck },
            { title: 'Đối soát vận chuyển', url: '/admin/shipping/reconciliation', icon: FileCheck },
        ],
    },
    {
        label: 'Vận hành',
        items: [
            { title: 'Danh sách kho', url: '/admin/warehouses', icon: Warehouse },
            { title: 'Kế toán', url: '/admin/accounting', icon: Wallet },
            { title: 'Thủ kho', url: '/admin/warehouse/operations', icon: ClipboardList },
            { title: 'Tồn kho SP', url: '/admin/warehouse/inventory', icon: Package },
            { title: 'Đơn lỗi', url: '/admin/orders/failed', icon: AlertTriangle },
        ],
    },
];

const roleMenus = {
    sales: [
        { title: 'Tác nghiệp telesale', url: '/sales/workspace', icon: PhoneCall },
        { title: 'Hồ sơ KH', url: '/sales/customers', icon: Home },
        { title: 'Cài đặt', url: '/settings', icon: Settings },
    ],
    marketing: [
        { title: 'Dashboard MKT', url: '/marketing/workspace', icon: Megaphone },
        { title: 'Chiến dịch MKT', url: '/marketing/campaigns', icon: Target },
        { title: 'Doanh thu MKT', url: '/marketing/revenue', icon: LineChart },
        { title: 'Cài đặt', url: '/settings', icon: Settings },
    ],
    warehouse: [
        { title: 'Tác nghiệp kho', url: '/warehouse/workspace', icon: Truck },
        { title: 'Tồn kho', url: '/warehouse/inventory', icon: Package },
        { title: 'Cài đặt', url: '/settings', icon: Settings },
    ],
    accounting: [
        { title: 'Tác nghiệp kế toán', url: '/accounting/workspace', icon: Wallet },
        { title: 'Cài đặt', url: '/settings', icon: Settings },
    ],
    allocator: [
        { title: 'Chia số & lead', url: '/allocator/workspace', icon: RefreshCw },
        { title: 'Cài đặt', url: '/settings', icon: Settings },
    ],
};

const fallbackItems = [
    { title: 'Cài đặt', url: '/settings', icon: Settings },
];

function isNavActive(itemUrl, currentUrl) {
    if (itemUrl === '/') {
        return currentUrl === '/';
    }

    return currentUrl === itemUrl || currentUrl.startsWith(`${itemUrl}/`);
}

export function AppSidebar() {
    const { props, url } = usePage();
    const { auth } = props;
    const isAdmin = auth.user?.role === 'admin';
    const roleLabel = auth.user?.role_label ?? 'Người dùng';
    const userItems = roleMenus[auth.user?.role] ?? fallbackItems;

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
                                            <SidebarMenuButton
                                                asChild
                                                tooltip={item.title}
                                                isActive={isNavActive(item.url, url)}
                                            >
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
                                {userItems.map((item) => (
                                    <SidebarMenuItem key={item.url}>
                                        <SidebarMenuButton
                                            asChild
                                            tooltip={item.title}
                                            isActive={isNavActive(item.url, url)}
                                        >
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
                                    <SidebarMenuButton asChild isActive={isNavActive('/settings', url)}>
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
                {isAdmin ? 'Quản trị hệ thống' : roleLabel}
            </SidebarFooter>
        </Sidebar>
    );
}
