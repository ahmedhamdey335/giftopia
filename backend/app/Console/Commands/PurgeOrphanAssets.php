<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PurgeOrphanAssets extends Command
{
    protected $signature = 'giftopia:purge-orphan-assets';

    protected $description = 'Delete inspiration assets that have been unattached for more than 24 hours.';

    public function handle(): int
    {
        $deleted = 0;
        Asset::query()
            ->whereNull('attached_at')
            ->where('created_at', '<', now()->subHours(24))
            ->orderBy('id')
            ->chunkById(100, function ($assets) use (&$deleted): void {
                foreach ($assets as $asset) {
                    if (! Storage::disk($asset->disk)->delete($asset->path)) {
                        throw new RuntimeException('Unable to delete orphan asset '.$asset->id.'.');
                    }

                    $asset->delete();
                    $deleted++;
                }
            });

        $this->info("Purged {$deleted} orphan assets.");

        return self::SUCCESS;
    }
}
