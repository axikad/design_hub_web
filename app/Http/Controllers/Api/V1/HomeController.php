<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Models\Design;
use App\Models\DesignCategory;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'sliderLimit' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'sectionLimit' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'categoryLimit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);
        $sliderLimit = $validated['sliderLimit'] ?? 5;
        $sectionLimit = $validated['sectionLimit'] ?? 6;
        $categoryLimit = $validated['categoryLimit'] ?? 4;
        $today = now(config('designhub.marketplace_timezone'))->toDateString();
        $tomorrow = now(config('designhub.marketplace_timezone'))->addDay()->toDateString();

        $latestQuery = $this->designQuery()->published($today);
        $todayQuery = $this->designQuery()->scheduledFor($today);
        $tomorrowQuery = $this->designQuery()->scheduledFor($tomorrow);

        $categories = DesignCategory::query()
            ->where('is_active', true)
            ->whereHas('designs', fn ($query) => $query->listed()->published($today))
            ->withCount([
                'designs as published_designs_count' => fn ($query) => $query->listed()->published($today),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (DesignCategory $category) use ($request, $categoryLimit, $today) {
                $designs = $category->designs()
                    ->listed()
                    ->published($today)
                    ->with(['owner', 'type.category', 'fileType', 'variants.type'])
                    ->orderByDesc('publish_date')
                    ->latest('created_at')
                    ->limit($categoryLimit)
                    ->get();

                return [
                    'id' => $category->public_id,
                    'name' => $category->name,
                    'total' => $category->published_designs_count,
                    'items' => $this->present($designs, $request),
                    'hasMore' => $category->published_designs_count > $designs->count(),
                    'viewMoreUrl' => route('api.v1.designs.index', ['categoryId' => $category->public_id]),
                ];
            })
            ->values();

        return ApiResponse::data([
            'latest' => $this->section(
                $latestQuery,
                $sliderLimit,
                $request,
                route('api.v1.designs.index', ['section' => 'latest']),
            ),
            'today' => $this->section(
                $todayQuery,
                $sectionLimit,
                $request,
                route('api.v1.designs.index', ['section' => 'today']),
            ),
            'tomorrow' => $this->section(
                $tomorrowQuery,
                $sectionLimit,
                $request,
                route('api.v1.designs.index', ['section' => 'tomorrow']),
            ),
            'categories' => $categories,
        ], message: 'Home designs retrieved successfully.');
    }

    private function designQuery(): Builder
    {
        return Design::query()
            ->listed()
            ->with(['owner', 'type.category', 'fileType', 'variants.type'])
            ->orderByDesc('publish_date')
            ->latest('created_at');
    }

    private function section(Builder $query, int $limit, Request $request, string $viewMoreUrl): array
    {
        $total = (clone $query)->count();
        $designs = $query->limit($limit)->get();

        return [
            'total' => $total,
            'items' => $this->present($designs, $request),
            'hasMore' => $total > $designs->count(),
            'viewMoreUrl' => $viewMoreUrl,
        ];
    }

    private function present(iterable $designs, Request $request): array
    {
        return collect($designs)
            ->map(fn (Design $design) => (new DesignResource($design))->resolve($request))
            ->values()
            ->all();
    }
}
