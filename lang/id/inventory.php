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

    // Activation Notifications
    'notification_activation_title' => 'Approval Aset Baru :name: Perlu Perhatian Anda',
    'notification_activation_message' => 'Aset baru dengan nomor :number memerlukan approval Anda.',
    'notification_bulk_activation_title' => 'Approval Aset Baru: :count Unit :name',
    'notification_bulk_activation_message' => 'Sebanyak :count unit aset baru untuk :name (:first s.d. :last) memerlukan approval Anda.',
    'notification_bulk_activation_single_message' => 'Sebanyak :count unit aset baru untuk :name (:number) memerlukan approval Anda.',
    'notification_activation_approved_title' => 'Aktivasi Aset :name Disetujui DM IFS',
    'notification_activation_approved_message' => 'Status aset :number telah berubah menjadi Tersedia.',
    'notification_activation_rejected_title' => 'Aktivasi Aset :name Ditolak DM IFS',
    'notification_activation_rejected_message' => 'Aktivasi aset :number ditolak oleh DM IFS. Status: Verifikasi Ditolak.',

    // Low Stock Alert
    'notification_low_stock_title' => 'Peringatan Stok Minimum: :name',
    'notification_low_stock_message' => 'Stok model :number saat ini :current :uom, telah mencapai atau di bawah batas minimum (:threshold :uom).',

    // Deactivation Notifications
    'notification_deactivation_title' => 'Penghapusan Aset :name: Perlu Perhatian Anda',
    'notification_deactivation_message' => 'Pengajuan penghapusan aset :number telah diajukan dan memerlukan approval Anda',
    'notification_bulk_deactivation_title' => 'Approval Penghapusan Aset: :count Unit (:condition)',
    'notification_bulk_deactivation_message' => 'Sebanyak :count unit aset diajukan untuk penghapusan (:condition) dan memerlukan approval Anda.',
    'notification_status_approved_title' => 'Penghapusan Aset :name Disetujui DM IFS',
    'notification_status_approved_message' => 'Status aset :number telah berubah menjadi Tidak Aktif, Pending:BoD/BoC.',
    'notification_status_rejected_title' => 'Penghapusan Aset :name Ditolak DM IFS',
    'notification_status_rejected_message' => 'Status aset :number telah dikembalikan menjadi :status dan kondisi dikembalikan menjadi :condition.',
];
