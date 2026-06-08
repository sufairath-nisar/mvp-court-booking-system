<?php

namespace App\Providers;

use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\CourtRepositoryInterface;
use App\Repositories\Contracts\CourtSlotRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Eloquent\BookingRepository;
use App\Repositories\Eloquent\CourtRepository;
use App\Repositories\Eloquent\CourtSlotRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Bind repository interfaces to their Eloquent implementations.
     *
     * Coding to interfaces (Dependency Inversion) lets the storage layer be swapped
     * — e.g. to a query-builder or test double — without touching the services.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        UserRepositoryInterface::class      => UserRepository::class,
        CourtRepositoryInterface::class     => CourtRepository::class,
        CourtSlotRepositoryInterface::class => CourtSlotRepository::class,
        BookingRepositoryInterface::class   => BookingRepository::class,
    ];

    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
