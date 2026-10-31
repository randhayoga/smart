<?php

namespace App\Http\Controllers\Smart\Admin\ManualRequest;

use App\Actions\Request\ProcessManualRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Smart\StoreManualRequestRequest;

/**
 * Controller handling manual borrowing (ST), assignment (LT), and consumable request creation.
 * Adheres strictly to Cruddy by Design (standard store action).
 */
class ManualRequestController extends Controller
{
    public function store(StoreManualRequestRequest $request, ProcessManualRequest $processAction)
    {
        $smartRequest = $processAction->execute(
            $request->validated(),
            $request->file('form_file'),
            (int) $request->user()->id
        );

        $successMessage = "Peminjaman/Permintaan manual ({$smartRequest->request_number}) berhasil disimpan.";

        if ($request->wantsJson() && !$request->header('X-Inertia')) {
            return response()->json([
                'message' => $successMessage,
                'request_id' => $smartRequest->id,
                'request_number' => $smartRequest->request_number,
            ], 201);
        }

        return redirect()->back()->with('success', $successMessage);
    }
}
