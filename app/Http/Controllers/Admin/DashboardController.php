<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Design;
use App\Models\Purchase;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard', [
            'stats' => [
                'users' => User::query()->whereNotNull('phone_key')->count(),
                'designs' => Design::query()->listed()->count(),
                'purchases' => Purchase::query()->where('status', 'paid')->count(),
                'revenuePaise' => (int) Purchase::query()->where('status', 'paid')->sum('amount_paise'),
            ],
        ]);
    }
}
