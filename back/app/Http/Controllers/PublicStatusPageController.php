<?php

namespace App\Http\Controllers;

use App\Models\StatusPage;
use App\Services\PublicStatusPagePresenter;
use Illuminate\Http\JsonResponse;

class PublicStatusPageController extends Controller
{
    public function __invoke(string $slug, PublicStatusPagePresenter $presenter): JsonResponse
    {
        $statusPage = StatusPage::query()->where('slug', $slug)->where('is_published', true)->firstOrFail();

        return response()
            ->json(['data' => $presenter->present($statusPage)])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
