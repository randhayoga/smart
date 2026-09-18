<?php

namespace App\Models;

/**
 * HRD Employee model extending the core User model for backward compatibility with existing HRIS references.
 */
class HrdEmployee extends User
{
    // Inherits all properties, relations, and methods from App\Models\User mapped to USER_HRIS.dbo.hrd_employee

    public function getMorphClass(): string
    {
        return User::class;
    }
}
