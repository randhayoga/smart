<?php

namespace App\Http\Controllers\Smart\MultiRoles;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Scan Barcode Controller providing interactive barcode and QR code scanning UI.
 */
class ScanBarcodeController extends Controller
{
    /**
     * Display the in-app QR code scanner page.
     */
    public function show(): Response
    {
        return Inertia::render('Smart/MultiRoles/PindaiBarcode');
    }
}
