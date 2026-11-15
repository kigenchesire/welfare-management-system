<?php

use App\Support\Settings;

if (! function_exists('setting')) {
    function setting(string $key, $default = null)
    {
        return Settings::get($key, $default);
    }
}

if (! function_exists('money')) {
    /** Format an amount with the configured currency, e.g. "KES 1,100.00". */
    function money($amount, bool $withCurrency = true): string
    {
        $formatted = number_format((float) $amount, 2);

        return $withCurrency ? Settings::get('currency', 'KES').' '.$formatted : $formatted;
    }
}

if (! function_exists('status_badge')) {
    /** Bootstrap badge class for the various record statuses. */
    function status_badge(?string $status): string
    {
        return match ($status) {
            'active', 'paid', 'confirmed', 'sent', 'fully_collected', 'closed' => 'text-bg-success',
            'pending', 'partially_paid', 'in_progress' => 'text-bg-warning',
            'open' => 'text-bg-primary',
            'unpaid', 'failed', 'rejected', 'suspended' => 'text-bg-danger',
            default => 'text-bg-secondary',
        };
    }
}
