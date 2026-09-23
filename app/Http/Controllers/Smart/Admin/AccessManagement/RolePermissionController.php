<?php

namespace App\Http\Controllers\Smart\Admin\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RolePermissionController extends Controller
{
    /**
     * Update/toggle a permission grant for a role.
     */
    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permission' => ['required', 'string', 'exists:SMART.permissions,name'],
            'granted' => ['sometimes', 'boolean'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $isGranted = $request->boolean('granted', $request->boolean('enabled'));

        if ($isGranted) {
            $role->givePermissionTo($validated['permission']);
        } else {
            $role->revokePermissionTo($validated['permission']);
        }

        return back()->with('success', "Hak akses '{$validated['permission']}' untuk peran '{$role->label}' berhasil diperbarui.");
    }
}
