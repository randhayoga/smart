<?php

namespace App\Http\Controllers\Smart\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class HandoverController extends Controller
{
    public function index()
    {
        return Inertia::render('Smart/Admin/SerahTerima', [
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }

    public function show($id)
    {
        // Mock data for handover
        $handover = [
            'id' => $id,
            'number' => '052026-000' . $id,
            'requester' => 'John Doe',
            'method' => 'Diambil sendiri',
            'time' => '12-05-2026 10:00',
            'location' => 'Ruang IFS'
        ];

        return Inertia::render('Smart/Admin/SerahTerimaDetail', [
            'handover' => $handover,
            'user' => [
                'name' => auth()->user()->name,
                'email' => auth()->user()->email,
            ]
        ]);
    }
}
