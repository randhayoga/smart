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
    ArrowLeftRight,
    FileClock,
    ShieldCheck,
} from 'lucide-vue-next';

export interface NavItem {
    id?: string;
    title: string;
    titleKey?: string;
    href: string;
    icon: any;
    badge?: string | number;
    children?: NavItem[];
    permission?: string | string[];
    role?: string | string[];
}

export interface NavSection {
    id?: string;
    title?: string;
    titleKey?: string;
    items: NavItem[];
    permission?: string | string[];
    role?: string | string[];
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
                permission: ['dashboard.admin.view', 'dashboard.user.view'],
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
                permission: 'inventory.manage',
            },
            {
                id: 'consumable_stock',
                title: 'Daftar Stok (Habis Pakai)',
                titleKey: 'nav.items.consumableStock',
                href: '/smart/inventory/stok-habis-pakai',
                icon: Scroll,
                permission: 'inventory.view',
            },
            {
                id: 'assets',
                title: 'Daftar Aset',
                titleKey: 'nav.items.assets',
                href: '/smart/inventory/assets',
                icon: ScrollText,
                permission: 'inventory.view',
            },
            {
                id: 'employees',
                title: 'Daftar Karyawan',
                titleKey: 'nav.items.employees',
                href: '/smart/karyawan',
                icon: Users,
                permission: 'karyawan.view',
            },
            {
                id: 'pending_inactive',
                title: 'Daftar Pending Nonaktif',
                titleKey: 'nav.items.pendingInactive',
                href: '/smart/inventory/pending-nonaktif',
                icon: Shredder,
                permission: 'inventory.status_approval.request',
            },
            {
                id: 'master_data',
                title: 'Master Data',
                titleKey: 'nav.items.masterData',
                href: '/smart/master',
                icon: Database,
                permission: 'master.view',
            },
            {
                id: 'scan_barcode',
                title: 'Pindai Barcode',
                titleKey: 'nav.items.scanBarcode',
                href: '/smart/scan',
                icon: QrCode,
                permission: ['inventory.manage', 'inventory.borrow'],
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
                permission: ['requests.inbox.view', 'requests.fulfill'],
            },
            {
                id: 'archive',
                title: 'Arsip',
                titleKey: 'nav.items.archive',
                href: '/smart/arsip',
                icon: Archive,
                permission: 'requests.archive.view',
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
                title: 'Pergerakan Aset',
                titleKey: 'nav.items.auditTrail',
                href: '/smart/audit',
                icon: ArrowLeftRight,
                permission: 'audit.view',
            },
            {
                id: 'inventory_audit',
                title: 'Audit Manajemen Stok',
                titleKey: 'nav.items.inventoryAudit',
                href: '/smart/audit-stok',
                icon: FileClock,
                permission: 'audit.view',
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

// Navigation for IFS Manager
export const ifsNavigation: NavSection[] = [
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
                permission: 'dashboard.admin.view',
            },
        ],
    },
    {
        id: 'stock',
        title: 'STOK',
        titleKey: 'nav.sections.stock',
        items: [
            {
                id: 'consumable_stock',
                title: 'Daftar Stok (Habis Pakai)',
                titleKey: 'nav.items.consumableStock',
                href: '/smart/inventory/stok-habis-pakai',
                icon: Scroll,
                permission: 'inventory.view',
            },
            {
                id: 'assets',
                title: 'Daftar Aset',
                titleKey: 'nav.items.assets',
                href: '/smart/inventory/assets',
                icon: ScrollText,
                permission: 'inventory.view',
            },
            {
                id: 'employees',
                title: 'Daftar Karyawan',
                titleKey: 'nav.items.employees',
                href: '/smart/karyawan',
                icon: Users,
                permission: 'karyawan.view',
            },
        ],
    },
    {
        id: 'approval_deletion',
        title: 'APPROVAL PENGHAPUSAN',
        titleKey: 'nav.sections.approvalDeletion',
        permission: 'inventory.status_approval.decide',
        items: [
            {
                id: 'need_approval_status',
                title: 'Perlu Approval',
                titleKey: 'nav.items.needApproval',
                href: '/smart/approve-status',
                icon: FileX,
                permission: 'inventory.status_approval.decide',
            },
            {
                id: 'processed_status',
                title: 'Sudah Diproses',
                titleKey: 'nav.items.processed',
                href: '/smart/approve-status?history=true',
                icon: Shredder,
                permission: 'inventory.status_approval.decide',
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
                title: 'Pergerakan Aset',
                titleKey: 'nav.items.auditTrail',
                href: '/smart/audit',
                icon: ArrowLeftRight,
                permission: 'audit.view',
            },
            {
                id: 'inventory_audit',
                title: 'Audit Manajemen Stok',
                titleKey: 'nav.items.inventoryAudit',
                href: '/smart/audit-stok',
                icon: FileClock,
                permission: 'audit.view',
            },
        ],
    },
];

export const superadminSection: NavSection = {
    id: 'superadmin_section',
    title: 'SUPERADMIN',
    titleKey: 'nav.sections.superadmin',
    permission: 'access.manage',
    items: [
        {
            id: 'access_management',
            title: 'Access Management',
            titleKey: 'nav.items.accessManagement',
            href: '/smart/access',
            icon: ShieldCheck,
            permission: 'access.manage',
        },
    ],
};

