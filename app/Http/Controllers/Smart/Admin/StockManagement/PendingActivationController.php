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
 * Pending Activation Controller listing asset units pending manager activation approval.
 */
class PendingActivationController extends Controller
{
    public function __construct(
        private readonly StockInventoryQueryService $queryService
    ) {}

    /**
     * Display a listing of pending activation units.
     */
    public function index(Request $request): Response
    {
        $locations = Location::allWithFullName();
        $locationHierarchyMap = Location::hierarchyMap($locations);
        $projects = TbProject::optionsForSelection();
        $projectNoMap = TbProject::projectNumberMap($projects);

        $units = $this->queryService->getFlatUnits(
            ['only_unverified' => true],
            $locationHierarchyMap,
            $projectNoMap
        );

        return Inertia::render('Smart/Admin/StockManagement/PendingActivationList', [
            'user' => $request->user(),
            'units' => $units,
            'locations' => $locations,
            'organizers' => Organizer::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'projects' => $projects,
        ]);
    }
}
