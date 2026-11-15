<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Forms that change data include a one-time "_submission" token
 * (@include('partials.submission')). A second POST with the same token
 * - a double click, a browser refresh or a resend - is rejected.
 */
class PreventDuplicateSubmission
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->input('_submission');
        if ($request->isMethod('get') || ! is_string($token) || $token === '') {
            return $next($request);
        }

        $scope = $request->user() ? 'u'.$request->user()->id : 's'.$request->session()->getId();
        $key = 'submission:'.sha1($scope.'|'.$token);
        if (! Cache::add($key, true, now()->addHours(6))) {
            return redirect()->back()->with('error', 'This form was already submitted. Please check the results before trying again.');
        }

        $response = $next($request);

        // Let the user correct and resubmit the same form after a validation error.
        if ($request->session()->has('errors') && $response->isRedirection()) {
            Cache::forget($key);
        }

        return $response;
    }
}
