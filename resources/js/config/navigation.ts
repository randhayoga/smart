import {
    LayoutDashboard,
    Package,
    Bell,
    Shredder,
    HelpCircle,
    Database,
    LayoutList,
    Handshake,
    Telescope,
    ListCheck,
    FileClock,
    Eye,
    PackagePlus,
    RotateCw,
    ShoppingBasket,
    ShoppingCart,
    Archive,
    RefreshCcw,
    ScrollText,
    Scroll,
    FileX
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
        title: 'MENU UTAMA',
        items: [
            {
                title: 'Dashboard',
                href: '/smart/dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        title: 'STOK',
        items: [
            {
                title: 'Manajemen Barang',
                href: '/smart/inventory',
                icon: Package,
            },
            {
                title: 'Daftar Aset',
                href: '/smart/inventory/assets',
                icon: ScrollText,
            },
            {
                title: 'Daftar Stok (Habis Pakai)',
                href: '/smart/inventory/stok-habis-pakai',
                icon: Scroll,
            },
            {
                title: 'Master Data',
                href: '/smart/master',
                icon: Database,
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
                title: 'Partial',
                href: '/smart/partial',
                icon: RotateCw,
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
                href: '/smart/returns',
                icon: RefreshCcw,
            },
            {
                title: 'Arsip',
                href: '/smart/arsip',
                icon: Archive,
            },
        ],
    },
    {
        title: 'AUDIT',
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
        title: 'MENU UTAMA',
        items: [
            {
                title: 'Dashboard',
                href: '/smart/user/dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        title: 'APPROVAL PEMINJAMAN',
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
        title: 'APPROVAL PENGHAPUSAN',
        items: [
            {
                title: 'Perlu Approval',
                href: '/smart/approve-status',
                icon: FileX,
            },
            {
                title: 'Sudah Diproses',
                href: '/smart/approve-status?history=true',
                icon: Shredder,
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
