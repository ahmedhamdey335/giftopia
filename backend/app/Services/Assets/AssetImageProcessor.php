<?php

namespace App\Services\Assets;

use Illuminate\Http\UploadedFile;
use RuntimeException;

final class AssetImageProcessor
{
    /**
     * @return array{contents: string, mime: string, extension: string}
     */
    public function reencode(UploadedFile $file): array
    {
        $contents = $file->get();
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($contents);

        if (! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new RuntimeException('Only valid JPEG, PNG, and WebP image files are accepted.');
        }

        $dimensions = @getimagesizefromstring($contents);
        if ($dimensions === false) {
            throw new RuntimeException('The uploaded file is not a readable image.');
        }

        [$width, $height] = $dimensions;
        if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 50000000) {
            throw new RuntimeException('The image dimensions exceed the allowed limits.');
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('The uploaded image could not be decoded.');
        }

        $scale = min(1, 2048 / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if ($target === false) {
            imagedestroy($source);
            throw new RuntimeException('The uploaded image could not be processed.');
        }

        if ($mime !== 'image/jpeg') {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);
        }

        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        if ($mime === 'image/webp' && ! function_exists('imagewebp')) {
            imagedestroy($source);
            imagedestroy($target);
            throw new RuntimeException('WebP support is not enabled in the GD image library.');
        }
        ob_start();
        $encoded = match ($mime) {
            'image/jpeg' => imagejpeg($target, null, 85),
            'image/png' => imagepng($target, null, 6),
            'image/webp' => imagewebp($target, null, 85),
        };
        $output = ob_get_clean();
        imagedestroy($source);
        imagedestroy($target);

        if (! $encoded || ! is_string($output) || $output === '') {
            throw new RuntimeException('The uploaded image could not be re-encoded.');
        }

        return [
            'contents' => $output,
            'mime' => $mime,
            'extension' => match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            },
        ];
    }
}
