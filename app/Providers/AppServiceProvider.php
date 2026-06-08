<?php

namespace App\Providers;

use App\Events\BookingCancelled;
use App\Events\BookingCreated;
use App\Listeners\SendBookingCancelledNotification;
use App\Listeners\SendBookingCreatedNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerBookingNotifications();
        $this->registerRateLimiter();
    }

    /**
     * Booking lifecycle events -> mail notifications to the consumer.
     */
    private function registerBookingNotifications(): void
    {
        Event::listen(BookingCreated::class, SendBookingCreatedNotification::class);
        Event::listen(BookingCancelled::class, SendBookingCancelledNotification::class);
    }

    /**
     * Named "api" rate limiter applied to all API routes (see bootstrap/app.php).
     *
     * Authenticated callers are limited per-user; guests per-IP. Disabled under
     * the test environment so the suite is never throttled.
     */
    private function registerRateLimiter(): void
    {
        RateLimiter::for('api', function (Request $request) {
            if (app()->environment('testing')) {
                return Limit::none();
            }

            return $request->user()
                ? Limit::perMinute(120)->by('user:' . $request->user()->id)
                : Limit::perMinute(30)->by('ip:' . $request->ip());
        });
    }
}
