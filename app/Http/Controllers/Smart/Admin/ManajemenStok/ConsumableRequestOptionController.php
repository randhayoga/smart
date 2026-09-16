<?php

namespace App\Http\Controllers\Smart\Admin\ManajemenStok;

use App\Http\Controllers\Controller;
use App\Models\AdmUser;
use App\Models\HrdOrgchart;
use App\Models\TbProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller providing requester, department, and project options for consumable requests.
 * Adheres strictly to Cruddy by Design principles.
 */
class ConsumableRequestOptionController extends Controller
{
    /**
     * Display a listing of options for consumable request forms.
     */
    public function index(Request $request): JsonResponse
    {
        $targetUserId = $request->query('user_id');

        if ($targetUserId) {
            $user = AdmUser::with(['hrdEmployee.orgchart'])->find((int) $targetUserId);
            $userOrg = $user?->hrdEmployee?->orgchart
                ?? ($user?->hrdEmployee?->orgchart_id ? HrdOrgchart::find($user->hrdEmployee->orgchart_id) : null);

            $departments = $userOrg ? [
                [
                    'id' => (int) $userOrg->id,
                    'name' => $userOrg->org_code ? "{$userOrg->org_code} - {$userOrg->org_name}" : $userOrg->org_name,
                ]
            ] : [];

            $userEmployeeId = $user?->employee_id ?? $user?->username;
            $projects = $userEmployeeId
                ? TbProject::whereHas('assignProjects', function ($query) use ($userEmployeeId) {
                    $query->where('npk', $userEmployeeId)
                        ->where('DELETION', '0');
                })
                    ->orderBy('project_name')
                    ->get(['id_project', 'no_project', 'project_name'])
                    ->map(fn($p) => [
                        'id' => (int) ($p->id_project ?? $p->id),
                        'name' => $p->no_project ? "{$p->no_project} - {$p->project_name}" : $p->project_name,
                        'no_project' => $p->no_project,
                    ])
                    ->values()
                    ->all()
                : [];

            return response()->json([
                'departments' => $departments,
                'projects' => $projects,
            ]);
        }

        $users = AdmUser::select('id', 'name', 'username')
            ->with(['hrdEmployee.orgchart'])
            ->orderBy('name')
            ->get()
            ->map(function ($u) {
                $org = $u->hrdEmployee?->orgchart;
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'employee_id' => $u->employee_id,
                    'department' => $org ? [
                        'id' => (int) $org->id,
                        'name' => $org->org_code ? "{$org->org_code} - {$org->org_name}" : $org->org_name,
                    ] : null,
                ];
            });

        $departments = HrdOrgchart::select('id', 'org_name', 'org_code')
            ->whereNotNull('org_name')
            ->orderBy('org_name')
            ->get()
            ->map(fn($d) => [
                'id' => (int) $d->id,
                'name' => $d->org_code ? "{$d->org_code} - {$d->org_name}" : $d->org_name,
            ]);

        $projects = TbProject::orderBy('project_name')
            ->get()
            ->map(fn($p) => [
                'id' => (int) $p->id_project,
                'name' => $p->no_project ? "{$p->no_project} - {$p->project_name}" : $p->project_name,
                'no_project' => $p->no_project,
            ]);

        return response()->json([
            'users' => $users,
            'departments' => $departments,
            'projects' => $projects,
        ]);
    }
}
