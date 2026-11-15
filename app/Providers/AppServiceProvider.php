<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Support\Settings;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        // Branding and settings are available in every view as $s.
        View::composer('*', function ($view) {
            $view->with('s', Settings::all());
        });

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $view->with('unreadNotifications', $user
                ? AppNotification::where('user_id', $user->id)->whereNull('read_at')->latest()->limit(8)->get()
                : collect());
        });
    }
}
