<?php

namespace App\Support;

use Illuminate\Http\Request;

final class Pagination
{
    public static function perPage(Request $request, int $default = 20, int $maximum = 100): int
    {
        return min(max($request->integer('per_page', $default), 1), $maximum);
    }
}
