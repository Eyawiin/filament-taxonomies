<?php

namespace Workbench\App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Str;
use Orchestra\Workbench\Workbench;

/**
 * Local playground only: signs in the testbench.yaml workbench user instead of
 * redirecting guests to the login form. The login page itself stays reachable.
 */
class AuthenticateWorkbenchUser extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();
        $identifier = (string) Workbench::config('user');

        if (! $guard->check() && $identifier !== '') {
            $provider = $guard->getProvider();
            $user = Str::contains($identifier, '@')
                ? $provider->retrieveByCredentials(['email' => $identifier])
                : $provider->retrieveById($identifier);

            if ($user !== null) {
                $guard->login($user);
            }
        }

        parent::authenticate($request, $guards);
    }
}
