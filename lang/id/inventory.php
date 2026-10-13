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
    'bod_boc_uploaded' => 'Formulir Approval BoD/BoC berhasil diunggah dan status diubah menjadi Pending:DM.',
    'bulk_bod_boc_approved' => ':count aset terpilih berhasil disetujui BoD/BoC dan status diubah menjadi Pending:DM.',
    'borrow_saved' => 'Data peminjaman berhasil disimpan.',
    'borrow_completed' => 'Peminjaman selesai. Status aset kembali Tersedia.',
    'manual_request_recorded' => 'Permintaan manual berhasil dicatat.',
    'activation_approved' => 'Aset berhasil diaktivasi.',
    'activation_rejected' => 'Aktivasi aset berhasil ditolak.',
];
