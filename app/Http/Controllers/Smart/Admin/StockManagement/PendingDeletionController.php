<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Services\Inventory\StockInventoryQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pending Deletion Controller listing asset units pending disposal/deletion approvals.
 */
class PendingDeletionController extends Controller
{
    public function __construct(
        private readonly StockInventoryQueryService $queryService
    ) {}

    /**
     * Display a listing of pending inactive units (Pending:BoD/BoC and Pending:DM).
     */
    public function index(Request $request): Response
    {
        $locations = Location::allWithFullName();
        $locationHierarchyMap = Location::hierarchyMap($locations);
        $projects = TbProject::optionsForSelection();
        $projectNoMap = TbProject::projectNumberMap($projects);

        $units = $this->queryService->getFlatUnits(
            ['approval_statuses' => ['Pending:DM', 'Pending:BoD/BoC']],
            $locationHierarchyMap,
            $projectNoMap
        );

        return Inertia::render('Smart/Admin/StockManagement/PendingDeletionList', [
            'user' => $request->user(),
            'units' => $units,
            'locations' => $locations,
            'organizers' => Organizer::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'projects' => $projects,
        ]);
    }
}
