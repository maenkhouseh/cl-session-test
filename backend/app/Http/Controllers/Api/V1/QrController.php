<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\QrCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QrController extends Controller
{
    /** Public endpoint (no auth): renders a QR code for any http(s) URL. */
    public function show(Request $request, QrCodeService $qr): Response
    {
        $v = $request->validate([
            'url' => ['required', 'url:http,https', 'max:2048'],
            'size' => ['sometimes', 'integer', 'min:64', 'max:2048'],
            'fgColor' => ['sometimes', 'regex:/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i'],
            'bgColor' => ['sometimes', 'regex:/^#?([0-9a-f]{3}|[0-9a-f]{6})$/i'],
            'level' => ['sometimes', 'in:L,M,Q,H'],
            'format' => ['sometimes', 'in:svg,png'],
        ]);

        [$body, $mime] = $qr->render($v['url'], $v['size'] ?? 600, $v['fgColor'] ?? '000000', $v['bgColor'] ?? 'FFFFFF', $v['level'] ?? 'M', $v['format'] ?? 'svg');

        return response($body, 200, ['Content-Type' => $mime, 'Cache-Control' => 'public, max-age=31536000, immutable']);
    }
}
