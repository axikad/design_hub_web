<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageAdminController extends Controller
{
    public function index(): Response
    {
        $order = ['terms-and-conditions', 'privacy-policy', 'refund-policy', 'about-app'];
        $urls = [
            'terms-and-conditions' => route('content.terms'),
            'privacy-policy' => route('content.privacy'),
            'refund-policy' => route('content.refund'),
            'about-app' => route('content.about'),
        ];

        $pages = ContentPage::query()->get()->sortBy(
            fn (ContentPage $page) => array_search($page->slug, $order, true),
        )->values()->map(fn (ContentPage $page) => [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'content' => $page->content,
            'isActive' => $page->is_active,
            'publicUrl' => $urls[$page->slug] ?? null,
            'updatedAt' => $page->updated_at?->toIso8601String(),
        ]);

        return Inertia::render('admin/content-pages/index', ['pages' => $pages]);
    }

    public function update(Request $request, ContentPage $contentPage): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:2', 'max:160'],
            'content' => ['required', 'string', 'min:10', 'max:100000'],
            'isActive' => ['required', 'boolean'],
        ]);

        $contentPage->update([
            'title' => trim($validated['title']),
            'content' => trim($validated['content']),
            'is_active' => $validated['isActive'],
        ]);

        return back()->with('success', $contentPage->title.' saved.');
    }
}
