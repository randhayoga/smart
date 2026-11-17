<?php

return [
    'bulk_create_success' => 'Berhasil membuat :count aset secara otomatis.',

    // Model (sebelumnya Barang / Tipe)
    'model_created' => 'Model berhasil ditambahkan.',
    'model_updated' => 'Model berhasil diperbarui.',
    'model_cannot_delete_has_lots' => 'Model tidak dapat dihapus karena masih memiliki LOT terkait.',
    'model_deleted' => 'Model berhasil dihapus.',
    'bulk_model_updated' => ':count model terpilih berhasil diperbarui.',
    'bulk_model_deleted' => ':count model terpilih berhasil dihapus.',
    'bulk_model_deleted_partial' => ":deleted model terpilih berhasil dihapus.\n:undeleted model tidak dapat dihapus karena memiliki LOT terkait.",

    // Backward-compatibility keys for barang
    'barang_created' => 'Model berhasil ditambahkan.',
    'barang_updated' => 'Model berhasil diperbarui.',
    'barang_cannot_delete_has_lots' => 'Model tidak dapat dihapus karena masih memiliki LOT terkait.',
    'barang_deleted' => 'Model berhasil dihapus.',
    'bulk_barang_updated' => ':count model terpilih berhasil diperbarui.',
    'bulk_barang_deleted' => ':count model terpilih berhasil dihapus.',
    'bulk_barang_deleted_partial' => ":deleted model terpilih berhasil dihapus.\n:undeleted model tidak dapat dihapus karena memiliki LOT terkait.",

    // LOT
    'lot_created' => 'LOT berhasil ditambahkan.',
    'lot_updated' => 'LOT berhasil diperbarui.',
    'lot_cannot_delete_has_units' => 'LOT tidak dapat dihapus karena masih memiliki aset terkait.',
    'lot_cannot_delete_has_assets' => 'LOT tidak dapat dihapus karena masih memiliki aset terkait.',
    'lot_deleted' => 'LOT berhasil dihapus.',
    'bulk_lot_updated' => ':count LOT terpilih berhasil diperbarui.',
    'bulk_lot_deleted' => ':count LOT terpilih berhasil dihapus.',
    'bulk_lot_deleted_partial' => ":deleted LOT terpilih berhasil dihapus.\n:undeleted LOT tidak dapat dihapus karena masih memiliki aset terkait.",
    'bulk_lot_delete_partial_has_units' => ":deleted LOT terpilih berhasil dihapus.\n:undeleted LOT tidak dapat dihapus karena masih memiliki unit terkait.",

    // Aset (sebelumnya Unit)
    'asset_created' => 'Aset berhasil ditambahkan.',
    'asset_updated' => 'Aset berhasil diperbarui.',
    'asset_cannot_delete_has_history' => 'Aset tidak dapat dihapus karena sudah memiliki riwayat peminjaman/permintaan.',
    'asset_deleted' => 'Aset berhasil dihapus.',
    'bulk_asset_updated' => ':count aset terpilih berhasil diperbarui.',
    'asset_not_borrowable' => 'Hanya aset berstatus Tersedia atau sedang Dipinjam yang dapat diproses peminjamannya.',

    // Backward-compatibility keys for unit
    'unit_created' => 'Aset berhasil ditambahkan.',
    'unit_updated' => 'Aset berhasil diperbarui.',
    'unit_cannot_delete_has_history' => 'Aset tidak dapat dihapus karena sudah memiliki riwayat peminjaman/permintaan.',
    'unit_deleted' => 'Aset berhasil dihapus.',
    'bulk_unit_updated' => ':count aset terpilih berhasil diperbarui.',
    'unit_not_borrowable' => 'Hanya aset berstatus Tersedia atau sedang Dipinjam yang dapat diproses peminjamannya.',

    // Approval & Peminjaman
    'bod_boc_uploaded' => 'Formulir Approval BoD/BoC berhasil diunggah dan status diubah menjadi Dihapus.',
    'bulk_bod_boc_approved' => ':count aset terpilih berhasil disetujui BoD/BoC dan status diubah menjadi Dihapus.',
    'bod_boc_disapproved' => 'Formulir Penolakan BoD/BoC berhasil diunggah dan status dikembalikan.',
    'bulk_bod_boc_disapproved' => ':count aset terpilih ditolak oleh BoD/BoC dan status dikembalikan.',
    'borrow_saved' => 'Data peminjaman berhasil disimpan.',
    'borrow_completed' => 'Peminjaman selesai. Status aset kembali Tersedia.',
    'manual_request_recorded' => 'Permintaan manual berhasil dicatat.',
    'activation_approved' => 'Aset berhasil diaktivasi.',
    'activation_rejected' => 'Aktivasi aset berhasil ditolak.',
    'condition_good' => 'Bagus',
    'condition_damaged' => 'Rusak',
    'condition_total_damage' => 'Rusak Total',
    'condition_lost' => 'Hilang',
    'lostDocFile' => 'SK Kehilangan',

    // Activation Notifications
    'notification_activation_title' => 'Approval Aktivasi Aset Baru',
    'notification_activation_message' => '[:number] :brand :name telah diregistrasi dan memerlukan approval aktivasi Anda.',
    'notification_bulk_activation_title' => 'Approval Aktivasi Aset Massal',
    'notification_bulk_activation_message' => '[:first - :last] :brand :name (:count unit) telah diregistrasi dan memerlukan approval aktivasi Anda.',
    'notification_bulk_activation_single_message' => '[:number] :brand :name (1 unit) telah diregistrasi dan memerlukan approval aktivasi Anda.',
    'notification_activation_approved_title' => 'Aktivasi Aset Disetujui',
    'notification_activation_approved_message' => '[:number] :brand :name disetujui oleh :actor. Status sekarang Tersedia.',
    'notification_activation_approved_expired_message' => '[:number] :brand :name disetujui oleh :actor. Status sekarang Lisensi Kedaluwarsa.',
    'notification_activation_rejected_title' => 'Aktivasi Aset Ditolak',
    'notification_activation_rejected_message' => '[:number] :brand :name ditolak oleh :actor. Status sekarang Verifikasi Ditolak.',

    // Low Stock Alert
    'notification_low_stock_title' => 'Peringatan Stok Minimum',
    'notification_low_stock_message' => '[:number] :brand :name saat ini tersisa :current :uom, telah mencapai atau di bawah batas minimum (:threshold :uom).',

    // Marked as Total Damage Notification
    'notification_marked_total_damage_title' => 'Asset Ditandai Rusak Total',
    'notification_marked_total_damage_message' => '[:number] :brand :name ditandai rusak total oleh :actor. Tinjau dan lakukan pergantian status menjadi Rusak Total jika sesuai.',

    // Deactivation Notifications
    'notification_deactivation_title' => 'Approval Penghapusan Aset',
    'notification_deactivation_message' => '[:number] :brand :name diajukan untuk penghapusan (:condition) dan memerlukan approval Anda.',
    'notification_bulk_deactivation_title' => 'Approval Penghapusan Aset Massal',
    'notification_bulk_deactivation_message' => ':count unit :brand :name diajukan untuk penghapusan (:condition) dan memerlukan approval Anda.',
    'notification_status_approved_title' => 'Penghapusan Aset Disetujui',
    'notification_status_approved_message' => '[:number] :brand :name disetujui penghapusannya oleh :actor. Status sekarang Pending:BoD/BoC.',
    'notification_status_rejected_title' => 'Penghapusan Aset Ditolak',
    'notification_status_rejected_message' => '[:number] :brand :name ditolak penghapusannya oleh :actor. Status dikembalikan ke :status dan kondisi ke :condition.',

    // Notifikasi Kedaluwarsa Lisensi Software
    'renewal_reminder_suffix' => 'Hari',
    'renewal_reminder_disabled' => 'Tidak ada pengingat',
    'notification_license_expiring_title' => 'Lisensi Software Segera Kedaluwarsa',
    'notification_license_expiring_message' => '[:number] :brand :name akan kedaluwarsa dalam :days hari. Silakan koordinasikan perpanjangan.',
    'notification_license_expiring_today_title' => 'Lisensi Software Kedaluwarsa Hari Ini',
    'notification_license_expiring_today_message' => '[:number] :brand :name kedaluwarsa hari ini. Silakan koordinasikan perpanjangan.',
    'notification_license_expired_title' => 'Lisensi Software Kedaluwarsa',
    'notification_license_expired_message' => '[:number] :brand :name telah kedaluwarsa. Status telah diubah menjadi Lisensi Kedaluwarsa.',
];
