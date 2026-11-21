<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DesignResource;
use App\Http\Resources\FolderResource;
use App\Http\Resources\PurchaseResource;
use App\Http\Resources\UserResource;
use App\Models\Design;
use App\Models\Folder;
use App\Models\Machine;
use App\Models\OtpChallenge;
use App\Models\ProfileContactChallenge;
use App\Models\Purchase;
use App\Models\SavedDesign;
use App\Models\User;
use App\Services\MarketplacePaymentSettings;
use App\Services\UploadService;
use App\Support\ApiResponse;
use App\Support\Pagination;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function __construct(
        private readonly UploadService $uploads,
        private readonly MarketplacePaymentSettings $paymentSettings,
    ) {}

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

    public function machinePreferences(Request $request)
    {
        return ApiResponse::data(
            $this->machinePreferencePayload($request->user()),
            message: 'Machine preferences retrieved successfully.',
        );
    }

    public function updateMachinePreferences(Request $request)
    {
        $validated = $request->validate([
            'machineIds' => ['required', 'array', 'min:1', 'max:50'],
            'machineIds.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('machines', 'public_id')->where('is_active', true),
            ],
        ]);

        $machineIds = Machine::query()
            ->whereIn('public_id', $validated['machineIds'])
            ->where('is_active', true)
            ->pluck('id');

        $user = $request->user();
        $user->machines()->sync($machineIds);
        $user->unsetRelation('machines');

        return ApiResponse::data(
            $this->machinePreferencePayload($user),
            message: 'Machine preferences updated successfully.',
        );
    }

    public function destroyAccount(Request $request)
    {
        $validated = $request->validate([
            'confirmation' => ['required', 'string', Rule::in(['DELETE'])],
        ]);

        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return ApiResponse::error(
                'The Super Admin account cannot be deleted from the mobile API.',
                'protected_account',
                403,
            );
        }

        $avatarPath = $user->avatar_path;
        $phoneKey = $user->phone_key;

        DB::transaction(function () use ($user, $phoneKey) {
            ProfileContactChallenge::query()->where('user_id', $user->id)->delete();
            SavedDesign::query()->where('user_id', $user->id)->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            $user->tokens()->delete();

            if ($phoneKey !== null) {
                OtpChallenge::query()
                    ->where('phone_key', $phoneKey)
                    ->whereNull('consumed_at')
                    ->update(['consumed_at' => now()]);
            }

            $user->forceFill([
                'name' => 'Deleted User',
                'country_code' => null,
                'mobile_number' => null,
                'phone_key' => null,
                'email' => null,
                'email_verified_at' => null,
                'avatar_path' => null,
                'password' => null,
                'remember_token' => null,
                'status' => 'inactive',
            ])->save();

            $user->delete();
        });

        if ($avatarPath !== null) {
            Storage::disk('public')->delete($avatarPath);
        }

        return ApiResponse::success('Account deleted successfully.');
    }

    public function myDesigns(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
        ]);

        $designs = Design::query()
            ->listed()
            ->where('user_id', $request->user()->id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('title', 'like', '%'.$search.'%'))
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

    public function myFolders(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'string', 'max:120'],
        ]);

        $folders = Folder::query()
            ->where('user_id', $request->user()->id)
            ->with('previewFiles')
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->latest()
            ->paginate(Pagination::perPage($request))
            ->withQueryString();

        return ApiResponse::paginated(
            $folders,
            $folders->getCollection()->map(
                fn (Folder $folder) => (new FolderResource($folder))->resolve($request)
            )->values(),
            'User folders retrieved successfully.',
        );
    }

    public function purchases(Request $request)
    {
        $rows = Purchase::query()
            ->with(['design.owner'])
            ->where('buyer_id', $request->user()->id)
            ->where('status', 'paid')
            ->where('payment_environment', $this->paymentSettings->environment())
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

    private function machinePreferencePayload(User $user): array
    {
        $user->loadMissing('machines:id,public_id');
        $selectedIds = $user->machines->pluck('public_id');

        $machines = Machine::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Machine $machine) => [
                'id' => $machine->public_id,
                'name' => $machine->name,
                'imageUri' => $machine->imageUrl(),
                'designFormConfig' => $machine->normalizedDesignFormConfig(),
                'selected' => $selectedIds->contains($machine->public_id),
            ]);

        return [
            'machineFilteringActive' => $selectedIds->isNotEmpty(),
            'selectedMachineIds' => $selectedIds->values(),
            'machines' => $machines->values(),
        ];
    }
}
