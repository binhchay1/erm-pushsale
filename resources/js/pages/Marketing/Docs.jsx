import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import {
    BarChart3,
    BookOpen,
    Boxes,
    Camera,
    CheckCircle2,
    ChevronRight,
    ClipboardList,
    DatabaseZap,
    FileText,
    Megaphone,
    PhoneCall,
    Search,
    Settings2,
    ShieldCheck,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';

import { Seo } from '@/components/marketing/Seo';
import { useT } from '@/providers/I18nProvider';
import { cn } from '@/lib/utils';
import '../../../css/pushsale-docs-page.css';

const LOCALES = {
    vi: {
        guideTitle: 'Hướng dẫn sử dụng',
        search: 'Tìm kiếm tài liệu',
        shortcut: 'Ctrl K',
        links: ['Giới thiệu', 'Bảng giá', 'Đăng kí', 'Đăng nhập'],
        heroTitle: '7 bước để bắt đầu cùng ERM SaleOps',
        heroIntro: 'Tài liệu này gom toàn bộ luồng vận hành thật của hệ thống: từ khai báo sản phẩm, nhận data marketing, chia sale, chốt đơn, kho vận, đối soát tới báo cáo điều hành.',
        cta: 'Cùng bắt đầu nào!',
        onThisPage: 'On this page',
        copy: 'Copy',
        lastUpdated: 'Last updated today',
        next: 'Next: checklist chạy thử dữ liệu thật',
        screenshotTitle: 'Prompt cho AI tạo ảnh minh họa',
        screenshotIntro: 'Khi đã bật quyền truy cập staging cho AI, dùng các prompt này để trình duyệt AI tự mở trang, chụp màn hình, vẽ callout và xuất ảnh đưa vào docs.',
        accessTitle: 'Cho AI chụp ảnh không cần đăng nhập',
        accessBody: 'Chỉ bật trên staging/test, giới hạn host và tắt ngay sau khi chụp ảnh xong. Không bật tuỳ chọn này trên production thật.',
    },
    en: {
        guideTitle: 'User guide',
        search: 'Search documentation',
        shortcut: 'Ctrl K',
        links: ['Overview', 'Pricing', 'Register', 'Sign in'],
        heroTitle: '7 steps to start with ERM SaleOps',
        heroIntro: 'This guide covers the real operating flow: product setup, marketing data intake, lead routing, telesale, orders, warehouse, reconciliation and executive reports.',
        cta: 'Let’s start!',
        onThisPage: 'On this page',
        copy: 'Copy',
        lastUpdated: 'Last updated today',
        next: 'Next: real-data testing checklist',
        screenshotTitle: 'Prompts for AI screenshot illustrations',
        screenshotIntro: 'After enabling staging access for the browser agent, use these prompts so AI can open pages, capture screenshots, draw callouts and export images for this docs page.',
        accessTitle: 'Allow AI screenshots without login',
        accessBody: 'Enable this only on staging/test, restrict hosts, and turn it off right after screenshots are done. Do not enable it on real production traffic.',
    },
};

const SIDEBAR = [
    {
        title: 'Hướng dẫn sử dụng',
        items: [
            ['#start', '7 bước để bắt đầu cùng ERM SaleOps'],
            ['#screenshots', 'Prompt ảnh minh hoạ AI'],
            ['#access', 'Truy cập staging cho AI'],
            ['#role-guides', 'Hướng dẫn theo role/menu'],
        ],
    },
    {
        title: 'I. Hướng dẫn chi tiết',
        items: [
            ['#products', 'Sản phẩm & combo'],
            ['#marketing', 'Marketing & landing'],
            ['#allocation', 'Phân bổ data'],
            ['#sales', 'Tác nghiệp sale'],
            ['#warehouse', 'Kho & phiếu xuất nhập'],
            ['#reports', 'Báo cáo & đối soát'],
        ],
    },
    {
        title: 'II. Câu hỏi thường gặp',
        items: [
            ['#qa-duplicate', 'Vì sao không chia trùng số?'],
            ['#qa-stock', 'Khi nào tồn kho tự thay đổi?'],
            ['#qa-upsell', 'Upsale được gộp thế nào?'],
        ],
    },
];

const STEPS = [
    {
        id: 'products',
        icon: Boxes,
        title: 'Bước 1: Khai báo sản phẩm, combo và giá bán',
        owner: 'Admin / Kho',
        purpose: 'Tạo danh mục sản phẩm chuẩn để marketing gắn vào landing, sale chốt đúng hàng và kho trừ đúng tồn.',
        points: [
            'Khai báo SKU, đơn vị tính, giá bán, giá vốn, trạng thái kinh doanh và nhóm sản phẩm.',
            'Tạo combo hoặc gói sản phẩm nếu chiến dịch bán theo set/upsale.',
            'Kiểm tra sản phẩm được bật cho Marketing, Sale và CSKH trước khi chạy data thật.',
        ],
    },
    {
        id: 'marketing',
        icon: Megaphone,
        title: 'Bước 2: Kết nối nguồn marketing và ngân sách',
        owner: 'Marketing / Admin duyệt',
        purpose: 'Tạo landing/source nhận lead thật, gắn sản phẩm, ngân sách và rule duyệt trước khi cho data đổ vào hệ thống.',
        points: [
            'Tạo kết nối Landing/Ladipage/Website/Facebook/Pancake theo từng chiến dịch.',
            'Gắn sản phẩm chính, sản phẩm upsale, ngân sách chạy và người phụ trách marketing.',
            'Admin duyệt nguồn trước khi webhook được coi là hợp lệ để tránh data rác.',
        ],
    },
    {
        id: 'allocation',
        icon: DatabaseZap,
        title: 'Bước 3: Phân bổ data cho sale',
        owner: 'Admin / Trưởng nhóm sale',
        purpose: 'Đưa lead mới tới đúng nhân sự, tránh chia trùng số điện thoại và giữ lịch sử toàn bộ packet landing/upsell.',
        points: [
            'Áp dụng round-robin, ưu tiên theo team/skill hoặc chia thủ công khi cần.',
            'Một số điện thoại chỉ được lock cho một sale tại một thời điểm để tránh tranh chấp đơn.',
            'Lead muộn, upsell quá hạn hoặc đơn đã khóa được đưa vào hàng chờ review thay vì tự gộp bừa.',
        ],
    },
    {
        id: 'sales',
        icon: PhoneCall,
        title: 'Bước 4: Sale tác nghiệp và chốt đơn',
        owner: 'Sale / Trưởng nhóm sale',
        purpose: 'Sale gọi khách, ghi nhận kết quả, hẹn chăm sóc, chốt đơn và chỉ sinh mã đơn sau khi chốt thành công.',
        points: [
            'Mỗi lần gọi có trạng thái, kết quả, ghi chú, tin nhắn cần lưu và lịch hẹn tiếp theo.',
            'Khi chốt đơn, sale chọn sản phẩm, số lượng, địa chỉ nhận hàng, phương thức giao và thông tin COD.',
            'Hồ sơ khách hàng gom lịch sử mua, tác nghiệp sale, tin nhắn nội bộ và chat Pancake theo quyền.',
        ],
    },
    {
        id: 'warehouse',
        icon: Warehouse,
        title: 'Bước 5: Kho, phiếu nhập/xuất và vận chuyển',
        owner: 'Kho / Admin',
        purpose: 'Kho nhận đơn đã chốt, tạo phiếu xuất nhập, liên kết vận chuyển và cập nhật tồn kho theo trạng thái thực tế.',
        points: [
            'Menu 5.3.1 là phiếu nhập / xuất kho chuẩn, dùng cho nhập hàng, xuất hàng và điều chỉnh tồn.',
            'Đơn chốt thành công tự tạo nhu cầu xuất kho; đơn hoàn cập nhật nhập lại tồn và chi phí hoàn.',
            'Webhook giao vận cập nhật trạng thái, COD, phí ship và dữ liệu đối soát cho kế toán.',
        ],
    },
    {
        id: 'reports',
        icon: BarChart3,
        title: 'Bước 6: Báo cáo, xếp hạng và đối soát',
        owner: 'Admin / Kế toán / Trưởng bộ phận',
        purpose: 'Theo dõi KPI thật theo ngày/tháng, đo hiệu quả marketing, sale, kho vận và dòng tiền.',
        points: [
            'Báo cáo sale: data nhận, cuộc gọi, đơn chốt, doanh thu, upsale và tỉ lệ chuyển đổi.',
            'Báo cáo marketing: ngân sách, nguồn data, chi phí/lead, đơn thành công và doanh số theo nguồn.',
            'Báo cáo kho/kế toán: tồn kho, vận đơn, COD, phí hoàn, đối soát nội bộ và sai lệch cần xử lý.',
        ],
    },
    {
        id: 'customize',
        icon: Settings2,
        title: 'Bước 7: Cấu hình quyền, menu và dữ liệu mẫu kiểm thử',
        owner: 'Admin hệ thống',
        purpose: 'Chốt quyền theo vai trò, kiểm tra menu theo từng bộ phận và chạy dữ liệu thật trước khi bàn giao khách hàng.',
        points: [
            'Admin, Sale và Kho có quyền thao tác nghiệp vụ; các vai trò khác xem theo phân quyền công ty.',
            'Menu hiển thị theo vai trò, không để role kho thấy sai báo cáo hoặc role marketing thao tác nhầm kho.',
            'Chạy seed/test flow để có data demo nhất quán, sau đó test như người dùng thật trên từng màn.',
        ],
    },
];

const SCREENSHOT_PROMPTS = [
    {
        title: 'Ảnh tổng quan điều hành',
        url: '/admin/dashboard',
        prompt: 'Mở trang /admin/dashboard trên domain staging. Chụp full-page screenshot. Vẽ callout số 1 vào dải KPI doanh thu, số 2 vào biểu đồ phễu sale, số 3 vào khu cảnh báo đơn/kho. Dùng nét vẽ màu đỏ, chữ ghi chú ngắn tiếng Việt, không che số liệu.',
    },
    {
        title: 'Ảnh luồng marketing nhận data',
        url: '/admin/marketing/landing-connections',
        prompt: 'Mở trang kết nối landing marketing. Chụp vùng filter + bảng. Khoanh vùng nút tạo kết nối, cột sản phẩm/nguồn, trạng thái duyệt và URL webhook. Gắn note giải thích: Marketing tạo nguồn, Admin duyệt, data mới bắt đầu chảy vào hệ thống.',
    },
    {
        title: 'Ảnh sale tác nghiệp',
        url: '/admin/sales/operations',
        prompt: 'Mở trang sale tác nghiệp bằng tài khoản demo. Chụp màn hình bảng data và modal cập nhật kết quả nếu có. Đánh dấu cột số điện thoại, trạng thái tác nghiệp, TN cần và nút chốt đơn. Ghi chú rằng mã đơn chỉ sinh sau khi chốt thành công.',
    },
    {
        title: 'Ảnh phiếu nhập / xuất kho 5.3.1',
        url: '/admin/warehouse/vouchers/entry',
        prompt: 'Mở /admin/warehouse/vouchers/entry. Chụp phần form phiếu nhập / xuất kho và bảng sản phẩm. Vẽ callout vào Loại phiếu, Kho, Sản phẩm, Số lượng, nút Hoàn thành và Cộng tồn kho. Ghi chú đây là màn chuẩn để nhập hàng, xuất hàng, điều chỉnh tồn.',
    },
    {
        title: 'Ảnh hồ sơ khách hàng',
        url: '/customers',
        prompt: 'Mở hồ sơ khách hàng. Chụp bảng khách hàng và các icon lịch sử/tin nhắn. Gắn callout vào địa chỉ, tin nhắn, lịch sử mua hàng, lịch sử telesale và chat Pancake. Ghi chú phân quyền: Admin/Sale/Kho được post, role khác xem theo quyền.',
    },
];

const ACCESS_COMMANDS = `# Bật trên staging để AI/browser agent truy cập màn admin không qua login
APP_DIR=/var/www/erm-pushsale \
DOMAIN=salesloop.vn \
ERM_AUTO_ADMIN_LOGIN_EMAIL=admin@saleops.local \
bash deploy/enable-ai-screenshot-access.sh

# Tắt ngay sau khi chụp xong
APP_DIR=/var/www/erm-pushsale bash deploy/disable-ai-screenshot-access.sh`;

const QA_ITEMS = [
    ['qa-duplicate', 'Vì sao không chia trùng số?', 'Vì số điện thoại là khóa vận hành của khách. Nếu một lead bị chia cho hai sale cùng lúc, lịch sử tác nghiệp, quyền xem data, chốt đơn và chăm sóc sau bán sẽ xung đột. Hệ thống phải lock số theo cửa sổ xử lý và đưa case nghi ngờ vào review.'],
    ['qa-stock', 'Khi nào tồn kho tự thay đổi?', 'Tồn thay đổi khi có phiếu nhập/xuất đã hoàn thành, đơn chốt cần xuất kho, đơn hoàn nhập lại kho hoặc webhook vận chuyển xác nhận trạng thái hoàn. Không cập nhật tồn bằng dữ liệu mẫu trên giao diện.'],
    ['qa-upsell', 'Upsale được gộp thế nào?', 'Upsell trong cửa sổ hợp lệ được gộp với lead/đơn chính theo submission reference. Upsell muộn, orphan upsell hoặc upsell sau khi sale đã khóa tác nghiệp sẽ chuyển review để người có quyền quyết định gộp hay tạo đơn bổ sung.'],
];

const ROLE_GUIDES = [
    ['Admin / Super Admin', 'Tạo đơn vị, tài khoản, phân quyền, duyệt nguồn landing, kiểm tra toàn bộ dashboard và báo cáo CEO. Luồng quan trọng: 1.1 → 1.2 → 1.3 → 1.5 → 1.7 → 7.CEO.'],
    ['Marketing', 'Tạo chiến dịch, kết nối landing/Facebook/Pancake, theo dõi ngân sách, chất lượng lead và báo cáo nguồn. Luồng quan trọng: 2.1 → 2.3 → 2.4 → 2.5 → báo cáo marketing.'],
    ['Sale / Telesale', 'Nhận data đã chia, gọi khách, ghi TN cần, hẹn chăm sóc, chốt đơn và xem lịch sử khách hàng. Luồng quan trọng: 4.Telesale → Hồ sơ khách hàng 360 → báo cáo sale.'],
    ['Kho', 'Nhận đơn đã chốt, tạo phiếu 5.3.1, cập nhật xuất/nhập, bàn giao vận chuyển và xử lý hoàn. Luồng quan trọng: 5.Kho → 5.3.1 Phiếu nhập/xuất → giao vận → tồn kho.'],
    ['Kế toán', 'Đối soát COD, phí vận chuyển, phí hoàn, doanh thu ghi nhận và báo cáo dòng tiền. Luồng quan trọng: 6.Kế toán → đối soát → báo cáo doanh thu.'],
];

const MENU_GUIDES = [
    ['1. Quản trị đơn vị', 'Cấu hình nền: thông tin đơn vị, nhân sự, sản phẩm, phân quyền sản phẩm, kết nối giao hàng, bảo mật và cấu hình Facebook.'],
    ['2. Marketing', 'Quản lý dashboard, xếp hạng, hồ sơ khách, nguồn landing/website/Facebook và tiện ích marketing.'],
    ['3. Khách hàng 360', 'Một màn gom thông tin khách: sale phụ trách, marketing nguồn, lịch sử mua, ghi chú, tin nhắn nội bộ và chat khách.'],
    ['4. Telesale', 'Màn làm việc chính của sale: nhận số, cập nhật kết quả, tạo/chốt đơn và theo dõi lịch sử tác nghiệp.'],
    ['5. Kho', 'Vận hành tồn kho, phiếu nhập/xuất, vận chuyển, hoàn hàng và trạng thái bàn giao.'],
    ['6. Kế toán', 'Đối soát tiền thu, COD chưa thu, chi phí, lợi nhuận và dữ liệu khớp báo cáo.'],
    ['7. CEO', 'Tổng hợp KPI điều hành, kế hoạch tháng/năm, ngân sách, doanh thu, chi phí, kiểm soát vận hành.'],
];


function useLocaleCopy() {
    const locale = String(usePage().props?.locale ?? 'vi').toLowerCase();
    return LOCALES[locale.startsWith('en') ? 'en' : 'vi'];
}

function Sidebar() {
    return (
        <aside className="ps-docs-sidebar hidden w-72 shrink-0 border-r border-slate-200 bg-slate-50/70 px-5 py-6 lg:block">
            <Link href="/" className="flex items-center gap-2 text-sm font-semibold text-slate-900">
                <BookOpen className="size-5 text-blue-600" />
                ERM SaleOps Docs
            </Link>
            <nav className="ps-docs-nav mt-8 space-y-7 text-sm">
                {SIDEBAR.map((group) => (
                    <section key={group.title} className="ps-docs-nav-section">
                        <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{group.title}</h3>
                        <div className="ps-docs-nav-items space-y-1">
                            {group.items.map(([href, label]) => (
                                <a key={href} href={href} className="ps-docs-tab flex items-center justify-between rounded-md px-2 py-1.5 text-slate-700 hover:bg-white hover:text-blue-700">
                                    <span>{label}</span>
                                    <ChevronRight className="size-3.5 text-slate-400" />
                                </a>
                            ))}
                        </div>
                    </section>
                ))}
            </nav>
            <div className="mt-10 rounded-xl border border-slate-200 bg-white p-4 text-xs leading-relaxed text-slate-600">
                <ShieldCheck className="mb-2 size-5 text-emerald-600" />
                Dữ liệu docs bám theo business ERM: lead thật, quyền thật, phiếu kho thật và báo cáo thật.
            </div>
        </aside>
    );
}

function Topbar({ copy, t }) {
    return (
        <header className="ps-docs-topbar sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
            <div className="flex h-16 items-center justify-between gap-4 px-4 lg:px-6">
                <div className="flex items-center gap-3 lg:hidden">
                    <BookOpen className="size-5 text-blue-600" />
                    <span className="font-semibold">{copy.guideTitle}</span>
                </div>
                <div className="hidden w-full max-w-xl items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 lg:flex">
                    <Search className="size-4" />
                    <span className="flex-1">{copy.search}</span>
                    <kbd className="rounded border border-slate-200 bg-white px-1.5 py-0.5 text-[11px]">{copy.shortcut}</kbd>
                </div>
                <nav className="flex items-center gap-4 text-sm text-slate-600">
                    <Link href="/about" className="hidden hover:text-blue-700 sm:inline">{copy.links[0]}</Link>
                    <Link href="/solutions" className="hidden hover:text-blue-700 sm:inline">{copy.links[1]}</Link>
                    <Link href="/contact" className="hidden hover:text-blue-700 sm:inline">{copy.links[2]}</Link>
                    <Link href="/login" className="font-semibold text-blue-700 hover:text-blue-800">{t('marketing.sign_in')}</Link>
                </nav>
            </div>
        </header>
    );
}

function StepBlock({ step }) {
    const Icon = step.icon;
    return (
        <section id={step.id} className="scroll-mt-24 border-t border-slate-200 pt-8">
            <div className="flex items-start gap-4">
                <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                    <Icon className="size-5" />
                </div>
                <div>
                    <h2 className="text-2xl font-bold tracking-tight text-slate-950">{step.title}</h2>
                    <p className="mt-1 text-sm text-slate-500">Tài khoản thiết lập: <b>{step.owner}</b></p>
                    <p className="mt-4 leading-7 text-slate-700">{step.purpose}</p>
                    <ul className="mt-4 space-y-3">
                        {step.points.map((point) => (
                            <li key={point} className="flex gap-3 leading-7 text-slate-700">
                                <CheckCircle2 className="mt-1 size-5 shrink-0 text-emerald-600" />
                                <span>{point}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </section>
    );
}

function PromptCard({ item }) {
    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start gap-3">
                <Camera className="mt-1 size-5 shrink-0 text-blue-700" />
                <div>
                    <h3 className="font-semibold text-slate-950">{item.title}</h3>
                    <code className="mt-1 block rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-700">{item.url}</code>
                </div>
            </div>
            <p className="mt-4 text-sm leading-7 text-slate-700">{item.prompt}</p>
        </article>
    );
}

function RightRail({ copy }) {
    const anchors = [
        ['#start', '7 bước bắt đầu'],
        ['#products', 'Sản phẩm'],
        ['#marketing', 'Marketing'],
        ['#allocation', 'Phân bổ data'],
        ['#sales', 'Sale'],
        ['#warehouse', 'Kho vận'],
        ['#reports', 'Báo cáo'],
        ['#screenshots', 'Ảnh minh hoạ'],
        ['#access', 'AI access'],
    ];
    return (
        <aside className="ps-docs-rail hidden w-60 shrink-0 px-6 py-8 xl:block">
            <div className="sticky top-24 text-sm">
                <h3 className="mb-3 font-semibold text-slate-900">{copy.onThisPage}</h3>
                <div className="space-y-2 border-l border-slate-200 pl-4">
                    {anchors.map(([href, label]) => (
                        <a key={href} href={href} className="block text-slate-500 hover:text-blue-700">{label}</a>
                    ))}
                </div>
            </div>
        </aside>
    );
}

export default function Docs({ seo }) {
    const copy = useLocaleCopy();
    const t = useT();

    useEffect(() => {
        const html = document.documentElement;
        const body = document.body;
        const previousClass = body.className;
        const previousHtmlOverflow = html.style.overflow;
        const previousBodyOverflow = body.style.overflow;

        body.classList.remove('pushsale-app-body', 'hold-transition', 'skin-blue-light', 'sidebar-mini', 'sidebar-collapse', 'fixed');
        body.classList.add('ps-docs-body');
        html.classList.add('ps-docs-html');
        html.style.overflow = 'auto';
        body.style.overflow = 'auto';

        return () => {
            body.className = previousClass;
            html.classList.remove('ps-docs-html');
            html.style.overflow = previousHtmlOverflow;
            body.style.overflow = previousBodyOverflow;
        };
    }, []);

    return (
        <div className="ps-docs-page min-h-screen bg-white text-slate-900">
            <Head title={seo?.title ?? copy.guideTitle} />
            <Seo seo={seo} />
            <div className="ps-docs-shell flex min-h-screen">
                <Sidebar />
                <div className="ps-docs-main flex min-w-0 flex-1 flex-col">
                    <Topbar copy={copy} t={t} />
                    <div className="ps-docs-content-wrap flex flex-1">
                        <main className="ps-docs-content min-w-0 flex-1 px-5 py-10 md:px-10 lg:px-14">
                            <article id="start" className="ps-docs-article mx-auto max-w-3xl scroll-mt-24">
                                <div className="mb-8 flex items-center gap-2 text-sm text-slate-500">
                                    <span>{copy.guideTitle}</span>
                                    <ChevronRight className="size-4" />
                                    <span>7 bước</span>
                                </div>
                                <div className="mb-4 flex items-center justify-between gap-4">
                                    <span className="inline-flex items-center gap-2 rounded-full bg-blue-50 px-3 py-1 text-sm font-medium text-blue-700">
                                        <ClipboardList className="size-4" /> Business docs
                                    </span>
                                    <button type="button" className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-500">
                                        <FileText className="size-3.5" /> {copy.copy}
                                    </button>
                                </div>
                                <h1 className="text-4xl font-bold tracking-tight text-slate-950 md:text-5xl">{copy.heroTitle}</h1>
                                <p className="mt-5 text-lg leading-8 text-slate-700">{copy.heroIntro}</p>
                                <p className="mt-4 font-semibold text-slate-900">{copy.cta}</p>

                                <div className="my-10 rounded-2xl border border-blue-100 bg-blue-50/70 p-5 text-sm leading-7 text-blue-950">
                                    <b>Luồng chuẩn:</b> Admin tạo công ty và tài khoản → Kho/Admin khai báo sản phẩm → Marketing tạo nguồn/landing → Admin duyệt → khách submit lead/upsell → hệ thống chống trùng và chia sale → sale tác nghiệp/chốt đơn → kho xử lý xuất nhập/vận chuyển → kế toán đối soát → báo cáo chốt số.
                                </div>

                                <div className="space-y-10">
                                    {STEPS.map((step) => <StepBlock key={step.id} step={step} />)}
                                </div>

                                <section id="role-guides" className="ps-docs-role-section mt-12 scroll-mt-24 border-t border-slate-200 pt-8">
                                    <div className="flex items-start gap-4">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-indigo-50 text-indigo-700">
                                            <Users className="size-5" />
                                        </div>
                                        <div>
                                            <h2 className="text-2xl font-bold tracking-tight text-slate-950">Hướng dẫn theo vai trò và menu</h2>
                                            <p className="mt-3 leading-7 text-slate-700">Phần này dùng để khách hàng đọc theo đúng menu đang thấy trên hệ thống. Khi training, chỉ cần chọn role/menu tương ứng rồi đi theo checklist bên dưới.</p>
                                        </div>
                                    </div>
                                    <div className="ps-docs-guide-grid mt-6">
                                        <div className="ps-docs-guide-card">
                                            <h3>Đi theo vai trò</h3>
                                            {ROLE_GUIDES.map(([role, body]) => (
                                                <div key={role} className="ps-docs-mini-item">
                                                    <b>{role}</b>
                                                    <p>{body}</p>
                                                </div>
                                            ))}
                                        </div>
                                        <div className="ps-docs-guide-card">
                                            <h3>Đi theo menu lớn</h3>
                                            {MENU_GUIDES.map(([menu, body]) => (
                                                <div key={menu} className="ps-docs-mini-item">
                                                    <b>{menu}</b>
                                                    <p>{body}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </section>

                                <section id="screenshots" className="mt-12 scroll-mt-24 border-t border-slate-200 pt-8">
                                    <div className="flex items-start gap-4">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                                            <Camera className="size-5" />
                                        </div>
                                        <div>
                                            <h2 className="text-2xl font-bold tracking-tight text-slate-950">{copy.screenshotTitle}</h2>
                                            <p className="mt-3 leading-7 text-slate-700">{copy.screenshotIntro}</p>
                                        </div>
                                    </div>
                                    <div className="mt-6 grid gap-4 md:grid-cols-2">
                                        {SCREENSHOT_PROMPTS.map((item) => <PromptCard key={item.title} item={item} />)}
                                    </div>
                                </section>

                                <section id="access" className="mt-12 scroll-mt-24 border-t border-slate-200 pt-8">
                                    <div className="flex items-start gap-4">
                                        <div className="flex size-11 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
                                            <ShieldCheck className="size-5" />
                                        </div>
                                        <div>
                                            <h2 className="text-2xl font-bold tracking-tight text-slate-950">{copy.accessTitle}</h2>
                                            <p className="mt-3 leading-7 text-slate-700">{copy.accessBody}</p>
                                        </div>
                                    </div>
                                    <pre className={cn('mt-6 overflow-x-auto rounded-2xl bg-slate-950 p-5 text-sm leading-7 text-slate-100')}><code>{ACCESS_COMMANDS}</code></pre>
                                </section>

                                <section className="mt-12 border-t border-slate-200 pt-8">
                                    <h2 className="text-2xl font-bold tracking-tight text-slate-950">Câu hỏi thường gặp</h2>
                                    <div className="mt-5 space-y-5">
                                        {QA_ITEMS.map(([id, question, answer]) => (
                                            <div key={id} id={id} className="scroll-mt-24 rounded-2xl border border-slate-200 p-5">
                                                <h3 className="font-semibold text-slate-950">{question}</h3>
                                                <p className="mt-2 leading-7 text-slate-700">{answer}</p>
                                            </div>
                                        ))}
                                    </div>
                                </section>

                                <footer className="mt-12 border-t border-slate-200 pt-6 text-sm text-slate-500">
                                    <p>{copy.lastUpdated}</p>
                                    <Link href="/features" className="mt-4 inline-flex items-center gap-2 font-semibold text-blue-700 hover:text-blue-800">
                                        {copy.next} <ChevronRight className="size-4" />
                                    </Link>
                                </footer>
                            </article>
                        </main>
                        <RightRail copy={copy} />
                    </div>
                </div>
            </div>
        </div>
    );
}
