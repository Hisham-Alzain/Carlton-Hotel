<?php

namespace App\Providers;

use App\Adapters\DirectAdapter;
use App\Contracts\ChannelAdapterInterface;
use App\Contracts\FirebaseServiceInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Models\PoolCabana;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use App\Payments\ManualDriver;
use App\Services\Firebase\FirebaseService;
use App\Services\Firebase\NullFirebaseService;
use App\Models\User;
use App\Policies\StaffPolicy;
use App\Validation\LiveRowPresenceVerifier;
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

        // `unique:` and `exists:` reach the database through the query builder,
        // so they never saw the soft-delete scope the CMS content models gained.
        // A deleted page would keep its slug reserved forever and a trashed
        // gallery chip would still pass `exists`. The verifier is the one place
        // both rules resolve through — see LiveRowPresenceVerifier for why it is
        // not `->whereNull('deleted_at')` on each rule.
        $this->app->extend(
            'validation.presence',
            fn ($verifier, $app) => new LiveRowPresenceVerifier($app['db']),
        );
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

        // Guest/User are NOT aliased here on purpose: Relation::morphMap() is
        // process-wide, and both models are also polymorphic causer/subject
        // targets for Spatie's ActivityLog (via LogsActivity, used by nearly
        // every model). Aliasing them would silently change causer_type/
        // subject_type for every audited model, not just Message::sender.
        // Message stores the FQCN directly; Message::senderLabel() maps it
        // back to "guest"/"staff" for API output.
        Relation::morphMap([
            'spa_service'      => SpaService::class,
            'restaurant_table' => RestaurantTable::class,
            'pool_cabana'      => PoolCabana::class,
            'transfer'         => Transfer::class,
        ]);
    }
}
