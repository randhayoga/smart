<?php

return [
    'bulk_create_success' => 'Successfully created :count assets automatically.',
    
    // Model (formerly Barang)
    'model_created' => 'Model successfully added.',
    'model_updated' => 'Model successfully updated.',
    'model_cannot_delete_has_lots' => 'Model cannot be deleted because it still has associated lots.',
    'model_deleted' => 'Model successfully deleted.',
    'bulk_model_updated' => ':count selected models successfully updated.',
    'bulk_model_deleted' => ':count selected models successfully deleted.',
    'bulk_model_deleted_partial' => ":deleted selected models successfully deleted.\n:undeleted models cannot be deleted because they have associated lots.",

    // Backward-compatibility keys for barang
    'barang_created' => 'Model successfully added.',
    'barang_updated' => 'Model successfully updated.',
    'barang_cannot_delete_has_lots' => 'Model cannot be deleted because it still has associated lots.',
    'barang_deleted' => 'Model successfully deleted.',
    'bulk_barang_updated' => ':count selected models successfully updated.',
    'bulk_barang_deleted' => ':count selected models successfully deleted.',
    'bulk_barang_deleted_partial' => ":deleted selected models successfully deleted.\n:undeleted models cannot be deleted because they have associated lots.",

    // LOT
    'lot_created' => 'LOT successfully added.',
    'lot_updated' => 'LOT successfully updated.',
    'lot_cannot_delete_has_units' => 'LOT cannot be deleted because it still has associated assets.',
    'lot_cannot_delete_has_assets' => 'LOT cannot be deleted because it still has associated assets.',
    'lot_deleted' => 'LOT successfully deleted.',
    'bulk_lot_updated' => ':count selected LOTs successfully updated.',
    'bulk_lot_deleted' => ':count selected LOTs successfully deleted.',
    'bulk_lot_deleted_partial' => ":deleted selected LOTs successfully deleted.\n:undeleted LOTs cannot be deleted because they still have associated assets.",
    'bulk_lot_delete_partial_has_units' => ":deleted selected LOTs successfully deleted.\n:undeleted LOTs cannot be deleted because they still have associated assets.",

    // Asset (formerly Unit)
    'asset_created' => 'Asset successfully added.',
    'asset_updated' => 'Asset successfully updated.',
    'asset_cannot_delete_has_history' => 'Asset cannot be deleted because it has borrowing/request history.',
    'asset_deleted' => 'Asset successfully deleted.',
    'bulk_asset_updated' => ':count selected assets successfully updated.',
    'asset_not_borrowable' => 'Only assets with "Available" or "Borrowed" status can be processed for borrowing.',

    // Backward-compatibility keys for unit
    'unit_created' => 'Asset successfully added.',
    'unit_updated' => 'Asset successfully updated.',
    'unit_cannot_delete_has_history' => 'Asset cannot be deleted because it has borrowing/request history.',
    'unit_deleted' => 'Asset successfully deleted.',
    'bulk_unit_updated' => ':count selected assets successfully updated.',
    'unit_not_borrowable' => 'Only assets with "Available" or "Borrowed" status can be processed for borrowing.',

    // Approvals & Borrowing
    'bod_boc_uploaded' => 'BoD/BoC Approval form successfully uploaded and status changed to Deleted.',
    'bulk_bod_boc_approved' => ':count selected assets successfully approved by BoD/BoC and status changed to Deleted.',
    'bod_boc_disapproved' => 'BoD/BoC Disapproval form successfully uploaded and status reverted.',
    'bulk_bod_boc_disapproved' => ':count selected assets rejected by BoD/BoC and status reverted.',
    'borrow_saved' => 'Borrowing data successfully saved.',
    'borrow_completed' => 'Borrowing completed. Asset status returned to Available.',
    'manual_request_recorded' => 'Manual request successfully recorded.',
    'activation_approved' => 'Asset successfully activated.',
    'activation_rejected' => 'Asset activation successfully rejected.',
    'condition_good' => 'Good',
    'condition_damaged' => 'Damaged',
    'condition_total_damage' => 'Total Damage',
    'condition_lost' => 'Lost',

    // Deactivation Notifications
    'notification_deactivation_title' => 'Asset Deletion :name: Requires Your Attention',
    'notification_deactivation_message' => 'Asset deletion request for :number has been submitted and requires your approval',
    'notification_bulk_deactivation_title' => 'Asset Deletion Approval: :count Units (:condition)',
    'notification_bulk_deactivation_message' => ':count asset units have been submitted for deletion (:condition) and require your approval.',
    'notification_status_approved_title' => 'Asset Deletion :name Approved by DM IFS',
    'notification_status_approved_message' => 'Asset :number status has changed to Inactive, Pending:BoD/BoC.',
    'notification_status_rejected_title' => 'Asset Deletion :name Rejected by DM IFS',
    'notification_status_rejected_message' => 'Asset :number status was reverted to :status and condition reverted to :condition.',
];
