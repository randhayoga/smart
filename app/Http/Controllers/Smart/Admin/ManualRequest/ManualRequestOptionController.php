<?php

namespace App\Http\Controllers\Smart\Admin\ManualRequest;

use App\Http\Controllers\Controller;
use App\Models\HrdOrgchart;
use App\Models\TbAssignProject;
use App\Models\TbProject;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * High-performance controller providing cascading employee, department, project, and DM/PM options
 * for the unified Manual Borrowing/Request modal using base SQL queries without Eloquent model hydration.
 */
class ManualRequestOptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        if ($request->filled('project_id')) {
            return response()->json(
                $this->resolveProjectApproverData((int) $request->input('project_id'))
            );
        }

        if ($request->filled('org_id') && !$request->filled('user_id')) {
            return response()->json(
                $this->resolveDepartmentApproverData((int) $request->input('org_id'))
            );
        }

        if ($request->filled('user_id')) {
            return response()->json(
                $this->resolveUserOptionsData((int) $request->input('user_id'))
            );
        }

        $employees = User::query()
            ->toBase()
            ->select('id', 'employee_id', 'employee_name', 'orgchart_id')
            ->where('active', 1)
            ->orderBy('employee_name')
            ->get()
            ->map(fn($u) => [
                'id' => (int) $u->id,
                'employee_id' => $u->employee_id,
                'orgchart_id' => $u->orgchart_id ? (int) $u->orgchart_id : null,
                'name' => $this->formatEmployeeLabel($u->employee_id, $u->employee_name),
            ])
            ->values();

        return response()->json([
            'employees' => $employees,
        ]);
    }

    /**
     * Resolve department, assigned projects, and pre-resolved corporate DM & candidates in a single round-trip.
     */
    private function resolveUserOptionsData(int $userId): array
    {
        $user = User::query()
            ->toBase()
            ->select('id', 'employee_id', 'employee_name', 'orgchart_id')
            ->where('id', $userId)
            ->first();

        if (!$user) {
            return [
                'departments' => [],
                'projects' => [],
                'auto_approver_id' => null,
                'approver_candidates' => [],
            ];
        }

        $departments = [];
        $autoApproverId = null;
        $approverCandidates = [];

        if (!empty($user->orgchart_id)) {
            $orgId = (int) $user->orgchart_id;
            $org = HrdOrgchart::query()
                ->toBase()
                ->select('id', 'org_name', 'employee_id')
                ->where('id', $orgId)
                ->whereNotNull('org_name')
                ->where('org_name', '!=', '')
                ->first();

            if ($org) {
                $departments[] = [
                    'id' => (int) $org->id,
                    'name' => $org->org_name,
                ];

                $deptApprover = $this->resolveDepartmentApproverFromOrg($org);
                $autoApproverId = $deptApprover['auto_approver_id'];
                $approverCandidates = $deptApprover['approver_candidates'];
            }
        }

        $projects = [];
        if (!empty($user->employee_id)) {
            $projects = TbProject::validForSelection()
                ->toBase()
                ->join('tb_assign_project', 'tb_project.no_project', '=', 'tb_assign_project.no_project')
                ->where('tb_assign_project.npk', $user->employee_id)
                ->where('tb_assign_project.DELETION', '0')
                ->select('tb_project.id_project', 'tb_project.no_project', 'tb_project.project_name')
                ->distinct()
                ->orderBy('tb_project.project_name')
                ->get()
                ->map(fn($p) => [
                    'id' => (int) $p->id_project,
                    'name' => $p->no_project ? "[{$p->no_project}] {$p->project_name}" : $p->project_name,
                ])
                ->values()
                ->all();
        }

        return [
            'departments' => $departments,
            'projects' => $projects,
            'auto_approver_id' => $autoApproverId,
            'approver_candidates' => $approverCandidates,
        ];
    }

    /**
     * Resolve active DM and all department employees for a given department ID.
     */
    private function resolveDepartmentApproverData(int $orgId): array
    {
        $org = HrdOrgchart::query()
            ->toBase()
            ->select('id', 'org_name', 'employee_id')
            ->where('id', $orgId)
            ->first();

        if (!$org) {
            return [
                'auto_approver_id' => null,
                'approver_candidates' => [],
            ];
        }

        return $this->resolveDepartmentApproverFromOrg($org);
    }

    /**
     * Resolve active DM and candidate employees from a pre-fetched orgchart stdClass row.
     */
    private function resolveDepartmentApproverFromOrg(object $org): array
    {
        $orgId = (int) $org->id;
        $dmRef = $org->employee_id ?? null;

        $deptUsers = User::query()
            ->toBase()
            ->select('id', 'employee_id', 'employee_name', 'orgchart_id')
            ->where('active', 1)
            ->where(function ($q) use ($orgId, $dmRef) {
                $q->where('orgchart_id', $orgId);
                if ($dmRef !== null && $dmRef !== '') {
                    if (is_numeric($dmRef)) {
                        $q->orWhere('id', (int) $dmRef);
                    }
                    $q->orWhere('employee_id', (string) $dmRef);
                }
            })
            ->orderBy('employee_name')
            ->get();

        $autoApproverId = null;
        if ($dmRef !== null && $dmRef !== '') {
            $matchedById = is_numeric($dmRef)
                ? $deptUsers->first(fn($u) => (int) $u->id === (int) $dmRef)
                : null;
            if ($matchedById) {
                $autoApproverId = (int) $matchedById->id;
            } else {
                $matchedByEmpId = $deptUsers->first(fn($u) => (string) $u->employee_id === (string) $dmRef);
                if ($matchedByEmpId) {
                    $autoApproverId = (int) $matchedByEmpId->id;
                }
            }
        }

        $candidates = $deptUsers->map(fn($u) => [
            'id' => (int) $u->id,
            'employee_id' => $u->employee_id,
            'name' => $this->formatEmployeeLabel($u->employee_id, $u->employee_name),
        ])->values()->all();

        return [
            'auto_approver_id' => $autoApproverId,
            'approver_candidates' => $candidates,
        ];
    }

    /**
     * Resolve active PM (id_rbs = P2211) and all assigned employees for a given project ID.
     */
    private function resolveProjectApproverData(int $projectId): array
    {
        $project = TbProject::query()
            ->toBase()
            ->select('id_project', 'no_project')
            ->where('id_project', $projectId)
            ->first();

        if (!$project || empty($project->no_project)) {
            return [
                'auto_approver_id' => null,
                'approver_candidates' => [],
            ];
        }

        $assignments = TbAssignProject::query()
            ->toBase()
            ->select('npk', 'id_rbs', 'start_date', 'id_assign')
            ->where('no_project', $project->no_project)
            ->where('DELETION', '0')
            ->orderByDesc('start_date')
            ->orderByDesc('id_assign')
            ->get();

        if ($assignments->isEmpty()) {
            return [
                'auto_approver_id' => null,
                'approver_candidates' => [],
            ];
        }

        $pmAssignment = $assignments->first(fn($a) => trim((string) $a->id_rbs) === 'P2211');
        $pmNpk = $pmAssignment ? trim((string) $pmAssignment->npk) : null;

        $uniqueNpks = $assignments
            ->pluck('npk')
            ->map(fn($npk) => trim((string) $npk))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $projectUsers = User::query()
            ->toBase()
            ->select('id', 'employee_id', 'employee_name')
            ->where('active', 1)
            ->whereIn('employee_id', $uniqueNpks)
            ->orderBy('employee_name')
            ->get();

        $autoApproverId = null;
        if ($pmNpk) {
            $pmUser = $projectUsers->first(fn($u) => trim((string) $u->employee_id) === $pmNpk);
            if ($pmUser) {
                $autoApproverId = (int) $pmUser->id;
            }
        }

        $candidates = $projectUsers->map(fn($u) => [
            'id' => (int) $u->id,
            'employee_id' => $u->employee_id,
            'name' => $this->formatEmployeeLabel($u->employee_id, $u->employee_name),
        ])->values()->all();

        return [
            'auto_approver_id' => $autoApproverId,
            'approver_candidates' => $candidates,
        ];
    }

    private function formatEmployeeLabel(?string $employeeId, ?string $employeeName): string
    {
        $id = trim((string) $employeeId);
        $name = trim((string) $employeeName);
        if ($id !== '' && $name !== '') {
            return "{$id} - {$name}";
        }
        return $name !== '' ? $name : ($id !== '' ? $id : '-');
    }
}
