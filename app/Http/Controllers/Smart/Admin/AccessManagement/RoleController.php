<?php

namespace App\Http\Controllers\Smart\Admin\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
    /**
     * System protected roles that cannot be deleted.
     */
    protected const PROTECTED_ROLES = [
        'superadmin',
        'admin',
        'ifs_manager',
        'manager',
        'user',
    ];

    /**
     * Store a newly created role.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_\-\s]+$/',
                'unique:SMART.roles,name',
            ],
        ]);

        $rawName = trim($validated['name']);
        $normalizedName = Str::slug($rawName, '_');
        $label = ucwords(str_replace(['_', '-'], ' ', $rawName));

        $role = Role::create([
            'name' => $normalizedName,
            'label' => $label,
            'description' => $request->input('description'),
        ]);

        return back()->with('success', "Peran '{$role->label}' berhasil dibuat.");
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-zA-Z0-9_\-\s]+$/',
                'unique:SMART.roles,name,' . $role->id,
            ],
        ]);

        $rawName = trim($validated['name']);
        $normalizedName = Str::slug($rawName, '_');
        $label = ucwords(str_replace(['_', '-'], ' ', $rawName));

        // Prevent renaming core system roles' identifier
        if (in_array($role->name, self::PROTECTED_ROLES, true) && $role->name !== $normalizedName) {
            $role->update(['label' => $label]);
        } else {
            $role->update([
                'name' => $normalizedName,
                'label' => $label,
                'description' => $request->input('description', $role->description),
            ]);
        }

        return back()->with('success', "Peran '{$role->label}' berhasil diperbarui.");
    }

    /**
     * Delete the specified role.
     */
    public function destroy(Role $role): RedirectResponse
    {
        // 1. Guard against deleting core system roles
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return back()->with('error', "Peran sistem '{$role->label}' dilindungi dan tidak dapat dihapus.");
        }

        // 2. Guard against deleting roles with active user assignments
        $activeUserCount = $role->users()->count();
        if ($activeUserCount > 0) {
            return back()->with('error', "Peran '{$role->label}' tidak dapat dihapus karena masih digunakan oleh {$activeUserCount} karyawan aktif.");
        }

        $roleName = $role->label;
        $role->permissions()->detach();
        $role->delete();

        return back()->with('success', "Peran '{$roleName}' berhasil dihapus.");
    }
}
