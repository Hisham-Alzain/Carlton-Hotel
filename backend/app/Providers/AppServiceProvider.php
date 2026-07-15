<?php

namespace App\Providers;

use App\Adapters\DirectAdapter;
use App\Contracts\ChannelAdapterInterface;
use App\Contracts\FirebaseServiceInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Models\Guest;
use App\Models\PoolCabana;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use App\Payments\ManualDriver;
use App\Services\Firebase\FirebaseService;
use App\Services\Firebase\NullFirebaseService;
use App\Models\User;
use App\Policies\StaffPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ChannelAdapterInterface::class, DirectAdapter::class);
        $this->app->bind(PaymentGatewayInterface::class, ManualDriver::class);
        $this->app->bind(FirebaseServiceInterface::class, function () {
            $project = config('firebase.default', 'app');
            return config("firebase.projects.{$project}.credentials")
                ? new FirebaseService()
                : new NullFirebaseService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Event::listen() calls for InquirySubmitted/ServiceRequestPlaced/RoomAssigned/
        // GuestConnected were removed here: Laravel auto-discovers app/Listeners'
        // handle(SpecificEvent $event) signatures, so the explicit registration was
        // firing every listener twice. See LOG_REPORT.md P9 for detail.
        Gate::before(fn ($user) => $user instanceof User && $user->isSuperAdmin() ? true : null);
        Gate::policy(User::class, StaffPolicy::class);

        Relation::morphMap([
            'spa_service'      => SpaService::class,
            'restaurant_table' => RestaurantTable::class,
            'pool_cabana'      => PoolCabana::class,
            'transfer'         => Transfer::class,
            'guest'            => Guest::class,
            'staff'            => User::class,
        ]);
    }
}
