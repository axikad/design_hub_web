<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DesignFiles\DesignFileInspector;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class DesignFileInspectorController extends Controller
{
    public function __construct(private readonly DesignFileInspector $inspector) {}

    public function index(): Response
    {
        return $this->page();
    }

    public function inspect(Request $request): Response
    {
        $validated = $request->validate([
            'designFile' => ['required', 'file'],
        ]);

        try {
            $result = $this->inspector->inspect($validated['designFile']);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'designFile' => $exception->getMessage(),
            ]);
        }

        return $this->page($result);
    }

    /** @param array<string, mixed>|null $result */
    private function page(?array $result = null): Response
    {
        return Inertia::render('admin/design-file-inspector/index', [
            'result' => $result,
            'supportedExtensions' => $this->inspector->supportedExtensions(),
        ]);
    }
}
