<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Auth\PublicSessionBootstrapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSessionController extends Controller
{
    public function __construct(
        private readonly PublicSessionBootstrapService $sessionBootstrap,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()
            ->json($this->sessionBootstrap->build($request))
            ->header('Cache-Control', 'private, no-store')
            ->header('Vary', 'Cookie');
    }
}
