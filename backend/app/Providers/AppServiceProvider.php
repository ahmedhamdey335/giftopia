<?php

namespace App\Providers;

use App\Models\BaseItem;
use App\Models\ColorSwatch;
use App\Models\Font;
use App\Models\Insert;
use App\Models\Mold;
use App\Models\Mood;
use App\Models\Order;
use App\Models\Technique;
use App\Models\TextColor;
use App\Models\User;
use App\Policies\OrderPolicy;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymobGateway;
use App\Services\Studio\FixedLeadTimeEstimator;
use App\Services\Studio\LeadTimeEstimator;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LeadTimeEstimator::class, FixedLeadTimeEstimator::class);
        $this->app->bind(PaymentGateway::class, function ($app): PaymentGateway {
            return match (config('services.payments.gateway')) {
                'fake' => $app->make(FakePaymentGateway::class),
                'paymob' => $app->make(PaymobGateway::class),
                default => throw new \InvalidArgumentException('Unsupported payment gateway configuration.'),
            };
        });
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);

        Relation::enforceMorphMap([
            'base_item' => BaseItem::class,
            'mold' => Mold::class,
            'mood' => Mood::class,
            'color_swatch' => ColorSwatch::class,
            'technique' => Technique::class,
            'insert' => Insert::class,
            'font' => Font::class,
            'text_color' => TextColor::class,
            'user' => User::class,
        ]);
    }
}
