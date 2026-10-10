<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function __construct(private readonly UploadService $uploads) {}

    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file'],
        ]);

        try {
            $upload = $this->uploads->store($request->user(), $request->file('file'));
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        return ApiResponse::data([
            'url' => $this->uploads->publicUrl($upload),
        ], 201, 'File uploaded successfully.');
    }
}
