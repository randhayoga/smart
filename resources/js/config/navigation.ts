/**
 * Application Navigation Configuration defining sidebar menu sections and routes for admin and user roles.
 */
import {
    LayoutDashboard,
    Package,
    Bell,
    Shredder,
    HelpCircle,
    Database,
    LayoutList,
    Archive,
    ScrollText,
    Scroll,
    FileX,
    QrCode,
    Users,
    Eye,
    ListCheck,
    PackagePlus,
    ShoppingBasket,
    ShoppingCart,
    FileClock,
} from 'lucide-vue-next';

export interface NavItem {
    id?: string;
    title: string;
    titleKey?: string;
    href: string;
    icon: any;
    badge?: string | number;
    children?: NavItem[];
}

export interface NavSection {
    id?: string;
    title?: string;
    titleKey?: string;
    items: NavItem[];
}

export const mainNavigation: NavSection[] = [
    {
        id: 'main_menu',
        title: 'MENU UTAMA',
        titleKey: 'nav.sections.mainMenu',
        items: [
            {
                id: 'dashboard',
                title: 'Dashboard',
                titleKey: 'nav.items.dashboard',
                href: '/smart/dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    {
        id: 'stock',
        title: 'STOK',
        titleKey: 'nav.sections.stock',
        items: [
            {
                id: 'inventory_management',
                title: 'Manajemen Barang',
                titleKey: 'nav.items.inventoryManagement',
                href: '/smart/inventory',
                icon: Package,
            },
            {
                id: 'consumable_stock',
                title: 'Daftar Stok (Habis Pakai)',
                titleKey: 'nav.items.consumableStock',
                href: '/smart/inventory/stok-habis-pakai',
                icon: Scroll,
            },
            {
                id: 'assets',
                title: 'Daftar Aset',
                titleKey: 'nav.items.assets',
                href: '/smart/inventory/assets',
                icon: ScrollText,
            },
            {
                id: 'employees',
                title: 'Daftar Karyawan',
                titleKey: 'nav.items.employees',
                href: '/smart/karyawan',
                icon: Users,
            },
            {
                id: 'pending_inactive',
                title: 'Daftar Pending Nonaktif',
                titleKey: 'nav.items.pendingInactive',
                href: '/smart/inventory/pending-nonaktif',
                icon: Shredder,
            },
            {
                id: 'master_data',
                title: 'Master Data',
                titleKey: 'nav.items.masterData',
                href: '/smart/master',
                icon: Database,
            },
            {
                id: 'scan_barcode',
                title: 'Pindai Barcode',
                titleKey: 'nav.items.scanBarcode',
                href: '/smart/scan',
                icon: QrCode,
            },
        ],
    },
    {
        id: 'requests',
        title: 'Permintaan',
        titleKey: 'nav.sections.requests',
        items: [
            {
                id: 'active_requests',
                title: 'Permintaan Aktif',
                titleKey: 'nav.items.activeRequests',
                href: '/smart/requests',
                icon: LayoutList,
                badge: undefined,
            },
            {
                id: 'archive',
                title: 'Arsip',
                titleKey: 'nav.items.archive',
                href: '/smart/arsip',
                icon: Archive,
            },
        ],
    },
    {
        id: 'audit',
        title: 'AUDIT',
        titleKey: 'nav.sections.audit',
        items: [
            {
                id: 'audit_trail',
                title: 'Jejak Audit',
                titleKey: 'nav.items.auditTrail',
                href: '/smart/audit',
                icon: FileClock,
            },
        ],
    },
];

export const quickActions = [
    {
        title: 'Notifications',
        titleKey: 'nav.quickActions.notifications',
        icon: Bell,
        badge: 3,
    },
    {
        title: 'Help',
        titleKey: 'nav.quickActions.help',
        icon: HelpCircle,
    },
];

// Navigation for regular users (non-admin)
export const userNavigation: NavSection[] = [
    // ==========================================
    // [PHASE 2 - REGULAR USER DASHBOARD]
    // Uncomment below when transitioning to Phase 2
    // ==========================================
    /*
    {
        id: 'main_menu',
        title: 'MENU UTAMA',
        titleKey: 'nav.sections.mainMenu',
        items: [
            {
                id: 'dashboard',
                title: 'Dashboard',
                titleKey: 'nav.items.dashboard',
                href: '/smart/user/dashboard',
                icon: LayoutDashboard,
            },
        ],
    },
    */
    // ==========================================

    // ==========================================
    // [PHASE 2 - REGULAR MANAGER BORROW APPROVAL]
    // Uncomment below when transitioning to Phase 2
    // ==========================================
    /*
    {
        id: 'approval_borrow',
        title: 'APPROVAL PEMINJAMAN',
        titleKey: 'nav.sections.approvalBorrow',
        items: [
            {
                id: 'need_approval',
                title: 'Perlu Approval',
                titleKey: 'nav.items.needApproval',
                href: '/smart/approve',
                icon: Eye,
            },
            {
                id: 'processed',
                title: 'Sudah Diproses',
                titleKey: 'nav.items.processed',
                href: '/smart/approved',
                icon: ListCheck,
            },
        ],
    },
    */
    // ==========================================

    {
        id: 'approval_deletion',
        title: 'APPROVAL PENGHAPUSAN',
        titleKey: 'nav.sections.approvalDeletion',
        items: [
            {
                id: 'need_approval_status',
                title: 'Perlu Approval',
                titleKey: 'nav.items.needApproval',
                href: '/smart/approve-status',
                icon: FileX,
            },
            {
                id: 'processed_status',
                title: 'Sudah Diproses',
                titleKey: 'nav.items.processed',
                href: '/smart/approve-status?history=true',
                icon: Shredder,
            },
        ],
    },

    // ==========================================
    // [PHASE 2 - REGULAR USER REQUESTS & CARTS]
    // Uncomment below when transitioning to Phase 2
    // ==========================================
    /*
    {
        id: 'requests',
        title: 'Permintaan',
        titleKey: 'nav.sections.requests',
        items: [
            {
                id: 'browse',
                title: 'Pilih Barang',
                titleKey: 'nav.items.browseItems',
                href: '/smart/browse',
                icon: PackagePlus,
            },
            {
                id: 'consumable_cart',
                title: 'Keranjang Habis Pakai',
                titleKey: 'nav.items.consumableCart',
                href: '/smart/asset-cart',
                icon: ShoppingBasket,
            },
            {
                id: 'borrow_cart',
                title: 'Keranjang Pinjam',
                titleKey: 'nav.items.borrowCart',
                href: '/smart/borrow-cart',
                icon: ShoppingCart,
            },
            {
                id: 'history',
                title: 'Riwayat',
                titleKey: 'nav.items.history',
                href: '/smart/history',
                icon: ListCheck,
            },
        ],
    },
    */
    // ==========================================
];
