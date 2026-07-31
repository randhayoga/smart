<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class MediaController extends Controller
{
    /**
     * Serve private media files securely for authenticated users.
     */
    public function show(string $path): Response
    {
        // Security: Prevent directory traversal attacks
        if (str_contains($path, '..')) {
            abort(400, 'Invalid file path.');
        }

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->response($path, null, [
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->response($path, null, [
                'Cache-Control' => 'private, max-age=86400',
            ]);
        }

        abort(404, 'File not found.');
    }
}
