<?php

use App\Services\Production\ProofService;
use App\Services\Reports\ReportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('giftopia:purge-orphan-assets')->hourly();
Schedule::command('giftopia:expire-unpaid-orders')->everyFifteenMinutes();

Artisan::command('giftopia:process-proofs', function (ProofService $proofs): void {
    $approved = $proofs->processDue();
    $reminded = $proofs->sendReminders();
    $this->info("Auto-approved {$approved} proof(s); sent {$reminded} reminder(s).");
})->purpose('Send proof reminders and auto-approve expired proofs');
Schedule::command('giftopia:process-proofs')->hourly();

Artisan::command('giftopia:report', function (ReportService $reports): void {
    $from = now()->startOfMonth();
    $to = now()->endOfDay();
    $this->line(json_encode($reports->overview($from, $to), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
})->purpose('Print the studio overview report');
