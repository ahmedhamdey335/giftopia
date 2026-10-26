<?php

namespace App\Services\Assets;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class AssetUploadService
{
    public function __construct(private readonly AssetImageProcessor $images) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<Asset>
     */
    public function uploadInspiration(array $files, ?User $owner): array
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($files, $owner, &$storedPaths): array {
                $assets = [];
                foreach ($files as $file) {
                    $image = $this->images->reencode($file);
                    $id = (string) Str::uuid();
                    $path = 'inspiration/'.$id.'.'.$image['extension'];
                    Storage::disk('private_assets')->put($path, $image['contents']);
                    $storedPaths[] = $path;

                    $assets[] = Asset::query()->create([
                        'id' => $id,
                        'kind' => 'inspiration',
                        'owner_user_id' => $owner?->id,
                        'disk' => 'private_assets',
                        'path' => $path,
                        'mime' => $image['mime'],
                        'size' => strlen($image['contents']),
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    ]);
                }

                return $assets;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('private_assets')->delete($path);
            }

            throw $exception;
        }
    }
}
