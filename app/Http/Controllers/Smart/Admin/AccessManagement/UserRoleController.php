<?php

namespace App\Http\Controllers\Smart\Admin\AccessManagement;

use App\Http\Controllers\Controller;
use App\Models\Auth\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserRoleController extends Controller
{
    /**
     * Update the assigned role for a user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'role' => [
                'required',
                'string',
                'exists:SMART.roles,name',
                Rule::notIn(['manager', 'ifs_manager']),
            ],
        ], [
            'role.not_in' => 'Managerial roles (Manager, IFS Manager) cannot be assigned manually and are synchronized directly from USER_HRIS.',
        ]);

        $user->syncRoles([$validated['role']]);

        $roleLabel = Role::where('name', $validated['role'])->value('label') ?? $validated['role'];
        $userName = $user->employee_name ?? $user->name;

        return back()->with('success', "Peran untuk {$userName} berhasil diubah menjadi {$roleLabel}.");
    }
}
