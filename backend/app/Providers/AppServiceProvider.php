<?php

namespace App\Providers;

use App\Adapters\DirectAdapter;
use App\Contracts\ChannelAdapterInterface;
use App\Contracts\PaymentGatewayInterface;
use App\Events\InquirySubmitted;
use App\Events\ServiceRequestPlaced;
use App\Listeners\MirrorServiceRequestToFirestore;
use App\Listeners\NotifyDepartmentOnInquiry;
use App\Models\PoolCabana;
use App\Models\RestaurantTable;
use App\Models\SpaService;
use App\Models\Transfer;
use App\Payments\ManualDriver;
use Illuminate\Support\Facades\Event;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(InquirySubmitted::class, NotifyDepartmentOnInquiry::class);
        Event::listen(ServiceRequestPlaced::class, MirrorServiceRequestToFirestore::class);
        Gate::before(fn ($user) => $user instanceof User && $user->isSuperAdmin() ? true : null);
        Gate::policy(User::class, StaffPolicy::class);

        Relation::morphMap([
            'spa_service'      => SpaService::class,
            'restaurant_table' => RestaurantTable::class,
            'pool_cabana'      => PoolCabana::class,
            'transfer'         => Transfer::class,
        ]);
    }
}
