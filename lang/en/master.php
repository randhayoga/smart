<?php

return [
    'categories' => [
        'created' => 'Category successfully added.',
        'updated' => 'Category successfully updated.',
        'cannot_delete_has_types' => 'Category cannot be deleted because it still has types.',
        'cannot_delete_has_subcategories' => 'Category cannot be deleted because it still has types.',
        'deleted' => 'Category successfully deleted.',
    ],
    'types' => [
        'created' => 'Type successfully added.',
        'updated' => 'Type successfully updated.',
        'cannot_delete_used' => 'Type cannot be deleted because it is being used by model data.',
        'deleted' => 'Type successfully deleted.',
    ],
    'subcategories' => [
        'created' => 'Type successfully added.',
        'updated' => 'Type successfully updated.',
        'cannot_delete_used' => 'Type cannot be deleted because it is being used by model data.',
        'deleted' => 'Type successfully deleted.',
    ],
    'brands' => [
        'created' => 'Brand successfully added.',
        'updated' => 'Brand successfully updated.',
        'cannot_delete_used' => 'Brand cannot be deleted because it is being used by model data.',
        'deleted' => 'Brand successfully deleted.',
    ],
    'uoms' => [
        'created' => 'Unit of measurement successfully added.',
        'updated' => 'Unit of measurement successfully updated.',
        'cannot_delete_used' => 'Unit of measurement cannot be deleted because it is being used by model data.',
        'deleted' => 'Unit of measurement successfully deleted.',
    ],
    'organizers' => [
        'created' => 'Organizer successfully added.',
        'updated' => 'Organizer successfully updated.',
        'cannot_delete_used' => 'Organizer cannot be deleted because it is being used by model lot data.',
        'deleted' => 'Organizer successfully deleted.',
    ],
    'vendors' => [
        'created' => 'Vendor successfully added.',
        'updated' => 'Vendor successfully updated.',
        'cannot_delete_used' => 'Vendor cannot be deleted because it is being used by model lot data.',
        'deleted' => 'Vendor successfully deleted.',
    ],
    'locations' => [
        'created' => 'Location successfully added.',
        'updated' => 'Location successfully updated.',
        'activated' => 'Location successfully activated.',
        'deactivated' => 'Location successfully deactivated.',
        'cannot_delete_has_children' => 'Location cannot be deleted because it still has sub-locations.',
        'cannot_delete_used' => 'Location cannot be deleted because it is being used by model lot/asset data.',
        'deleted' => 'Location successfully deleted.',
    ],
];
