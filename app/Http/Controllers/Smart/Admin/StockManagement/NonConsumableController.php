<?php

namespace App\Http\Controllers\Smart\Admin\StockManagement;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Master\Location;
use App\Models\Master\Organizer;
use App\Models\Master\Vendor;
use App\Models\TbProject;
use App\Services\Inventory\StockInventoryQueryService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * NonConsumable Controller rendering dedicated physical asset units listing (Daftar Tidak Habis Pakai).
 */
class NonConsumableController extends Controller
{
    public function __construct(
        private readonly StockInventoryQueryService $queryService
    ) {}

    /**
     * Display the non-consumable asset inventory listing page (Daftar Tidak Habis Pakai).
     */
    public function index(Request $request): Response
    {
        Asset::expireOverdueLicensesCached();

        $locations = Location::allWithFullName();
        $locationHierarchyMap = Location::hierarchyMap($locations);

        $unitsCompact = $this->queryService->getCompactUnitsPayload(
            ['exclude_unverified' => true],
            $locationHierarchyMap
        );

        return Inertia::render('Smart/Admin/StockManagement/NonConsumableList', [
            'user' => $request->user(),
            'units_compact' => $unitsCompact,
            'locations' => $locations,
            'organizers' => Organizer::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'vendors' => Vendor::query()->toBase()->orderBy('name')->get(['id', 'name']),
            'projects' => Inertia::lazy(fn () => TbProject::optionsForSelection()),
            'users' => Inertia::lazy(fn () => $this->queryService->getActiveUsersForSelection()),
        ]);
    }
}
