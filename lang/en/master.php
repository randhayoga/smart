<?php

return [
    'categories' => [
        'created' => 'Category successfully added.',
        'updated' => 'Category successfully updated.',
        'cannot_delete_has_subcategories' => 'Category cannot be deleted because it still has subcategories.',
        'deleted' => 'Category successfully deleted.',
    ],
    'subcategories' => [
        'created' => 'Subcategory successfully added.',
        'updated' => 'Subcategory successfully updated.',
        'cannot_delete_used' => 'Subcategory cannot be deleted because it is being used by item data.',
        'deleted' => 'Subcategory successfully deleted.',
    ],
    'brands' => [
        'created' => 'Brand successfully added.',
        'updated' => 'Brand successfully updated.',
        'cannot_delete_used' => 'Brand cannot be deleted because it is being used by item data.',
        'deleted' => 'Brand successfully deleted.',
    ],
    'uoms' => [
        'created' => 'Unit of measurement successfully added.',
        'updated' => 'Unit of measurement successfully updated.',
        'cannot_delete_used' => 'Unit of measurement cannot be deleted because it is being used by item data.',
        'deleted' => 'Unit of measurement successfully deleted.',
    ],
    'organizers' => [
        'created' => 'Organizer successfully added.',
        'updated' => 'Organizer successfully updated.',
        'cannot_delete_used' => 'Organizer cannot be deleted because it is being used by item lot data.',
        'deleted' => 'Organizer successfully deleted.',
    ],
    'vendors' => [
        'created' => 'Vendor successfully added.',
        'updated' => 'Vendor successfully updated.',
        'cannot_delete_used' => 'Vendor cannot be deleted because it is being used by item lot data.',
        'deleted' => 'Vendor successfully deleted.',
    ],
    'locations' => [
        'created' => 'Location successfully added.',
        'updated' => 'Location successfully updated.',
        'activated' => 'Location successfully activated.',
        'deactivated' => 'Location successfully deactivated.',
        'cannot_delete_has_children' => 'Location cannot be deleted because it still has sub-locations.',
        'cannot_delete_used' => 'Location cannot be deleted because it is being used by item lot/unit data.',
        'deleted' => 'Location successfully deleted.',
    ],
];
