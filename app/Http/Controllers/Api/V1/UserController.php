<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Http\Resources\PurchaseResource;
use App\Http\Resources\UserResource;
use App\Models\Design;
use App\Models\Purchase;
use App\Models\SavedDesign;
use App\Services\UploadService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(private readonly UploadService $uploads) {}

    public function me(Request $request)
    {
        return ApiResponse::data(
            (new UserResource($request->user()))->resolve($request),
            message: 'User retrieved successfully.',
        );
    }

    public function update(Request $request)
    {
        $user = $request->user();

        if ($request->hasAny(['email', 'countryCode', 'mobileNumber'])) {
            throw ValidationException::withMessages([
                'contact' => 'Email and mobile number changes require OTP verification. Use the profile contact OTP APIs.',
            ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:120'],
            'image' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'removeImage' => ['sometimes', 'boolean'],
        ]);

        if ($request->hasFile('image')) {
            $upload = $this->uploads->store($user, $request->file('image'));
            $upload->update(['attached' => true]);
            $validated['avatar_path'] = $upload->path;
        } elseif (($validated['removeImage'] ?? false) === true) {
            $validated['avatar_path'] = null;
        }

        unset($validated['image'], $validated['removeImage']);

        $user->fill($validated);

        $user->save();

        return ApiResponse::data(
            (new UserResource($user->fresh()))->resolve($request),
            message: 'Profile updated successfully.',
        );
    }

    public function myDesigns(Request $request)
    {
        $designs = Design::query()
            ->listed()
            ->where('user_id', $request->user()->id)
            ->with(['owner', 'type.category'])
            ->latest('created_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $designs,
            $designs->getCollection()->map(
                fn (Design $design) => (new DesignResource($design))->resolve($request)
            )->values(),
            'User designs retrieved successfully.',
        );
    }

    public function purchases(Request $request)
    {
        $rows = Purchase::query()
            ->with(['design.owner'])
            ->where('buyer_id', $request->user()->id)
            ->where('status', 'paid')
            ->latest('purchased_at')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $rows,
            $rows->getCollection()->map(
                fn (Purchase $purchase) => (new PurchaseResource($purchase))->resolve($request)
            )->values(),
            'Purchases retrieved successfully.',
        );
    }

    public function savedDesigns(Request $request)
    {
        $savedDesigns = SavedDesign::query()
            ->with('design.owner', 'design.type.category')
            ->where('user_id', $request->user()->id)
            ->whereHas('design', fn ($query) => $query->listed())
            ->latest('id')
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $savedDesigns,
            $savedDesigns->getCollection()->map(
                fn (SavedDesign $saved) => (new DesignResource($saved->design))->resolve($request)
            )->values(),
            'Saved designs retrieved successfully.',
        );
    }

    public function saveDesign(Request $request, string $designId)
    {
        $design = Design::query()->listed()->where('public_id', $designId)->firstOrFail();

        SavedDesign::query()->firstOrCreate([
            'user_id' => $request->user()->id,
            'design_id' => $design->id,
        ]);

        return ApiResponse::success('Design saved successfully.');
    }

    public function unsaveDesign(Request $request, string $designId)
    {
        $design = Design::query()->where('public_id', $designId)->firstOrFail();

        SavedDesign::query()
            ->where('user_id', $request->user()->id)
            ->where('design_id', $design->id)
            ->delete();

        return ApiResponse::success('Design removed from saved designs.');
    }
}
