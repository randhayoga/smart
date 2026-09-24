/**
 * Indonesian Access Management translation dictionary.
 */
export default {
  title: 'Manajemen Akses',
  tabs: {
    users: 'Manajemen Pengguna',
    roles: 'Manajemen Peran',
  },
  users: {
    title: 'Manajemen Pengguna',
    searchLabel: 'Cari Pengguna',
    searchPlaceholder: 'Cari NPK atau Nama Pengguna',
    filterRoleLabel: 'Filter Peran',
    allRoles: 'Semua Peran',
    rowsPerPage: 'Baris per halaman',
    allRows: 'Semua baris',
    syncHris: 'Sinkronisasi dari USER_HRIS',
    syncing: 'Menyinkronkan...',
    userColumn: 'Pengguna',
    roleColumn: 'Peran',
    syncSuccess: 'Berhasil menyinkronkan data manager dan karyawan non-manajerial dari USER_HRIS.',
    syncError: 'Gagal menyinkronkan manager dari HRIS.',
    roleUpdateSuccess: 'Peran pengguna berhasil diperbarui.',
    roleUpdateError: 'Gagal memperbarui peran pengguna.',
  },
  roles: {
    title: 'Manajemen Peran',
    createNewRole: 'Buat Peran Baru',
    rowsPerPage: 'Baris per halaman',
    allRows: 'Semua baris',
    rolesColumn: 'Peran',
    actionColumn: 'Aksi',
    systemBadge: 'Sistem',
    userCount: '{count} pengguna',
    editRole: 'Ubah Peran',
    deleteRole: 'Hapus Peran',
    groups: {
      dashboard: 'Dashboard',
      master: 'Master Data',
      inventory: 'Inventaris',
      requests: 'Permintaan',
      audit: 'Audit',
      access: 'Kontrol Akses',
      karyawan: 'Direktori Karyawan',
      notifications: 'Notifikasi',
      general: 'Umum',
    },
    permissions: {
      dashboard: {
        admin: {
          view: 'Lihat Dashboard Admin',
        },
        user: {
          view: 'Lihat Dashboard Pengguna',
        },
      },
      master: {
        view: 'Lihat Master Data',
        manage: 'Kelola Master Data (Kategori, Lokasi, dll.)',
      },
      inventory: {
        view: 'Lihat Katalog Inventaris, LOT & Aset',
        manage: 'Kelola Barang Inventaris, LOT, dan Unit',
        borrow: 'Lakukan Peminjaman & Pengembalian Unit Langsung',
        manual_request: 'Buat Permintaan Stok Manual',
        status_approval: {
          request: 'Ajukan Permintaan Perubahan Status Unit',
          decide: 'Setujui atau Tolak Perubahan Status Unit',
        },
      },
      requests: {
        create: 'Buat dan Ajukan Permintaan (Keranjang)',
        view_own: 'Lihat Riwayat Permintaan Sendiri & Batalkan',
        approve: 'Setujui atau Tolak Permintaan Bawahan',
        inbox: {
          view: 'Lihat Kotak Masuk Permintaan Disetujui',
        },
        confirm: 'Konfirmasi dan Tinjau Permintaan',
        fulfill: 'Alokasikan Unit/LOT dan Konfirmasi Pemenuhan',
        handover: 'Jadwalkan dan Proses Serah Terima Barang',
        returns: 'Proses dan Konfirmasi Pengembalian Barang',
        archive: {
          view: 'Lihat Arsip Permintaan',
        },
      },
      karyawan: {
        view: 'Lihat Direktori Karyawan dan Peminjaman Aktif',
      },
      audit: {
        view: 'Lihat Log Aktivitas Inventaris dan Audit Stok',
      },
      notifications: {
        manage: 'Lihat dan Kelola Notifikasi Pribadi',
      },
      access: {
        manage: 'Kelola Peran dan Hak Akses Sistem',
      },
    },
    tooltips: {
      superadminAll: 'Superadmin memiliki semua izin secara bawaan',
      togglePermission: 'Alihkan {name}',
    },
    names: {
      superadmin: 'Super Administrator',
      admin: 'Administrator',
      ifs_manager: 'IFS Manager',
      manager: 'Manager Departemen / Proyek',
      user: 'Karyawan',
    },
    disabledDeleteReasons: {
      systemRole: 'Peran sistem dilindungi dan tidak dapat dihapus.',
      hasUsers: 'Tidak dapat menghapus peran yang ditetapkan ke {count} pengguna.',
    },
    modals: {
      createTitle: 'Buat Peran Baru',
      editTitle: 'Ubah Peran',
      roleName: 'Nama Peran',
      roleNamePlaceholder: 'contoh: Quality Auditor',
      editRoleNamePlaceholder: 'Nama peran',
      requiredField: '* Kolom wajib diisi',
      createButton: 'Buat Peran',
      saveChanges: 'Simpan Perubahan',
      cancel: 'Batal',
      deleteConfirmItemName: 'Peran',
      createSuccess: 'Peran berhasil dibuat.',
      updateSuccess: 'Peran berhasil diperbarui.',
      deleteSuccess: 'Peran berhasil dihapus.',
      deleteErrorDefault: 'Gagal menghapus peran.',
      permissionGranted: 'Hak akses berhasil diberikan.',
      permissionRevoked: 'Hak akses berhasil dicabut.',
      permissionUpdateError: 'Gagal memperbarui hak akses.',
      validation: {
        roleNameRequired: 'Nama peran wajib diisi.',
      },
      errors: {
        protectedRoleDelete: "Peran '{name}' adalah peran yang dilindungi sistem dan tidak dapat dihapus.",
        roleHasUsersDelete: "Peran '{name}' tidak dapat dihapus karena saat ini masih ditetapkan ke {count} pengguna aktif. Harap alihkan semua pengguna sebelum menghapus peran ini.",
      },
    },
  },
};
