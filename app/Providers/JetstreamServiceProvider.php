<?php

namespace App\Providers;

use App\Actions\Jetstream\DeleteUser;
use App\Events\LoginTracker;
use App\Listeners\LoginListener;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;
use Laravel\Jetstream\Jetstream;

class JetstreamServiceProvider extends ServiceProvider
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
        $this->configurePermissions();

        Jetstream::deleteUsersUsing(DeleteUser::class);

        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where('username', $request->username)
                ->orWhere('email', $request->username)->first();

            if ($user && Hash::check($request->password, $user->password)) {
                // LoginTracker::dispatch('success', $user->user_id);
                return $user;
            } else {
                // LoginTracker::dispatch('failed', $user->user_id);
            }
        });
    }

    /**
     * Configure the permissions that are available within the application.
     */
    protected function configurePermissions(): void
    {
        Jetstream::defaultApiTokenPermissions(['read']);

        Jetstream::permissions([
            'create',
            'read',
            'update',
            'delete',
        ]);
    }
}
