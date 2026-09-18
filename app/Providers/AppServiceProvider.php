<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\URL;
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
        Vite::prefetch(concurrency: 3);
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Register HRIS-aware Eloquent user provider supporting legacy MD5 hashes and modern Bcrypt
        \Illuminate\Support\Facades\Auth::provider('eloquent', function ($app, array $config) {
            return new class($app['hash'], $config['model']) extends \Illuminate\Auth\EloquentUserProvider {
                public function validateCredentials(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials): bool
                {
                    $plain = (string) ($credentials['password'] ?? '');
                    $hash = (string) ($user->getAuthPassword() ?? '');

                    if ($hash === '') {
                        return false;
                    }

                    // 1. MD5 hash check (USER_HRIS.dbo.adm_user standard: 32 hex characters)
                    if (strlen($hash) === 32 && ctype_xdigit($hash)) {
                        return hash_equals(strtolower($hash), md5($plain));
                    }

                    // 2. Fallback to standard Laravel hasher (e.g. bcrypt for tests/factories)
                    return parent::validateCredentials($user, $credentials);
                }

                public function rehashPasswordIfRequired(\Illuminate\Contracts\Auth\Authenticatable $user, array $credentials, bool $force = false): void
                {
                    // USER_HRIS.dbo.hrd_employee has no password column. Prevent automatic password rehash query.
                }
            };
        });
    }
}
