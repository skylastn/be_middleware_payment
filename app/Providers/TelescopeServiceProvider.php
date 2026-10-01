<?php

namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            if (($entry->type === 'query' && Str::contains($entry->content['sql'] ?? '', ['payment_repositories', 'projects']))
                || ($entry->type === 'redis' && Str::contains($entry->content['command'] ?? '', ['agi:snap_token:', 'bank_agi:outbound_token:', 'payment_token:', 'project:token:']))) {
                return false;
            }

            return $isLocal ||
                $entry->isReportableException() ||
                $entry->isFailedRequest() ||
                $entry->isFailedJob() ||
                $entry->isScheduledTask() ||
                $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        Telescope::hideRequestParameters(['_token', 'password', 'value', 'token', 'client_secret', 'bank_client_secret', 'private_key']);

        Telescope::hideResponseParameters(['accessToken', 'data.value', 'data.token', 'data.project.value', 'data.payment_repository.value']);

        Telescope::hideRequestHeaders([
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'authorization',
            'token',
            'x_signature',
            'x-signature',
        ]);
    }

    /**
     * Register the Telescope gate.
     *
     * This gate determines who can access Telescope in non-local environments.
     */
    protected function gate(): void
    {
        Gate::define('viewTelescope', function ($user = null) {
            return $user && in_array($user->email, [config('telescope.allowed_email')]);
        });
    }
}
