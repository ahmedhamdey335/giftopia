<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Studio\AssetUploadRequest;
use App\Models\Asset;
use App\Services\Assets\AssetUploadService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/** Upload customer inspiration and serve files only through short-lived signed links. */
class AssetController extends Controller
{
    /** Store up to the request's allowed number of inspiration files and return their IDs and URLs. */
    public function store(AssetUploadRequest $request, AssetUploadService $uploads): JsonResponse
    {
        $assets = $uploads->uploadInspiration($request->file('files'), $request->user());

        return ApiResponse::success([
            'items' => collect($assets)->map(fn (Asset $asset): array => [
                'id' => $asset->id,
                'kind' => $asset->kind,
                'mime' => $asset->mime,
                'size' => $asset->size,
                'file_url' => URL::temporarySignedRoute(
                    'assets.file',
                    now()->addMinutes(15),
                    ['asset' => $asset->id],
                ),
            ])->all(),
        ], 'Inspiration assets uploaded.', 201);
    }

    /** Enforce ownership and asset-purpose rules before returning a stored file. */
    public function file(Request $request, Asset $asset): Response
    {
        $user = $request->user();
        if ($user !== null) {
            $role = $user->role;
            $allowed = in_array($role, [UserRole::Admin->value, UserRole::Producer->value], true)
                || ($role === UserRole::Customer->value
                    && ($asset->owner_user_id === $user->id
                        || $asset->orderItem?->order?->user_id === $user->id));

            abort_unless($allowed, 403);
        } else {
            abort_unless(
                $asset->kind === 'inspiration' && $asset->order_item_id === null,
                403,
            );
        }

        return response(Storage::disk($asset->disk)->get($asset->path), 200, [
            'Content-Type' => $asset->mime,
            'Content-Disposition' => 'inline; filename="'.$asset->id.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=900',
        ]);
    }
}
