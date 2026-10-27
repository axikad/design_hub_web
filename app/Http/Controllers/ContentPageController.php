<?php

namespace App\Http\Controllers;

use App\Models\ContentPage;
use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ContentPageController extends Controller
{
    public function show(string $slug): View
    {
        $page = ContentPage::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        return view('content-page', [
            'page' => $page,
            'branding' => SiteSetting::current()->toBranding(),
            'contentHtml' => Str::markdown($page->content, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]),
        ]);
    }
}
