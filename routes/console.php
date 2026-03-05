<?php

declare(strict_types=1);

use App\Jobs\FetchCommodityDataJob;
use App\Jobs\FetchRealmAuctionDataJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new FetchCommodityDataJob)->hourly();
Schedule::job(new FetchRealmAuctionDataJob)->hourlyAt(30);
