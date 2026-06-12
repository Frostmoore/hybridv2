<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serve le immagini per-agenzia agli stessi URL relativi del legacy
 * (/res/img/<id>/..., /res/img_<id>/...). I file vivono in
 * storage/app/agency-assets/ e arrivano dal vecchio server via rsync
 * (fase 9/11). In produzione conviene farli servire direttamente da nginx;
 * questa route garantisce comunque il funzionamento ovunque.
 */
class AssetController extends Controller
{
    public function show(Request $request, string $path): BinaryFileResponse
    {
        $base = storage_path('app/agency-assets');
        $full = realpath($base.DIRECTORY_SEPARATOR.$path);

        // Niente path traversal, niente file fuori dalla cartella
        if ($full === false || ! str_starts_with($full, realpath($base).DIRECTORY_SEPARATOR) || ! is_file($full)) {
            abort(404);
        }

        return response()->file($full);
    }
}
