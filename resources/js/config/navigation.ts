import {
    LayoutDashboard,
    Package,
    FileText,
    BarChart3,
    Settings,
    Users,
    Bell,
    HelpCircle,
    Database,
    LayoutList,
    Handshake,
    Telescope,
    BaggageClaim,
    ListCheck,
    FileClock,
    Eye,
    PackagePlus,
    ShoppingBasket,
    ShoppingCart
} from 'lucide-vue-next';

export interface NavItem {
    title: string;
    href: string;
    icon: any;
    badge?: string | number;
    children?: NavItem[];
}

export interface NavSection {
    title?: string;
    items: NavItem[];
}

export const mainNavigation: NavSection[] = [
    {
        title: 'Menu Utama',
        items: [
            {
                title: 'Dashboard',
                href: '/smart/dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        title: 'Inventory',
        items: [
            {
                title: 'Manajemen Stok',
                href: '/smart/inventory',
                icon: Package,
            },
            {
                title: 'Master Data',
                href: '/smart/master',
                icon: Database,
                // badge: 'New',
            },
        ],
    },
    {
        title: 'Permintaan',
        items: [
            {
                title: 'Inbox',
                href: '/smart/inbox',
                icon: LayoutList,
            },
            {
                title: 'Serah Terima',
                href: '/smart/handover',
                icon: Handshake,
            },
            {
                title: 'Lacak Peminjaman',
                href: '/smart/borrowed',
                icon: Telescope,
            },
            {
                title: 'Pengembalian',
                href: '/smart/returning',
                icon: BaggageClaim,
            },
            {
                title: 'Arsip',
                href: '/smart/archive',
                icon: ListCheck,
            },
        ],
    },
    {
        title: 'Audit',
        items: [
            {
                title: 'Jejak Audit',
                href: '/smart/audit',
                icon: FileClock,
            },
        ],
    },

];

export const quickActions = [
    {
        title: 'Notifications',
        icon: Bell,
        badge: 3,
    },
    {
        title: 'Help',
        icon: HelpCircle,
    },
];

// Navigation for regular users (non-admin)
export const userNavigation: NavSection[] = [
    {
        title: 'Menu Utama',
        items: [
            {
                title: 'Dashboard',
                href: '/smart/user/dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        title: 'Approval',
        items: [
            {
                title: 'Perlu Approval',
                href: '/smart/approve',
                icon: Eye,
            },
            {
                title: 'Sudah Diproses',
                href: '/smart/approved',
                icon: ListCheck,
            },
        ],
    },
    {
        title: 'Permintaan',
        items: [
            {
                title: 'Pilih Barang',
                href: '/smart/browse',
                icon: PackagePlus,
            },
            {
                title: 'Keranjang Habis Pakai',
                href: '/smart/asset-cart',
                icon: ShoppingBasket,
            },
            {
                title: 'Keranjang Pinjam',
                href: '/smart/borrow-cart',
                icon: ShoppingCart,
            },
            {
                title: 'Riwayat Permintaan',
                href: '/smart/history',
                icon: ListCheck,
            },
        ],
    },
];
