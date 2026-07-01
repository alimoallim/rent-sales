<?php

namespace App\Providers;

use App\Models\ChargeBatch;
use App\Models\BuildingElectricityBill;
use App\Models\BuildingWaterUtilityBill;
use App\Models\Client;
use App\Models\Employee;
use App\Models\PayrollEntry;
use App\Models\RentalBuilding;
use App\Models\RentalExpense;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\RentalUnit;
use App\Models\SaleBuilding;
use App\Models\SalesExpense;
use App\Models\SalesPayment;
use App\Models\SaleUnit;
use App\Models\Shareholder;
use App\Models\ShareholderBill;
use App\Models\Tenant;
use App\Models\TenantElectricityBill;
use App\Models\TenantWaterBill;
use App\Models\User;
use App\Policies\ChargeBatchPolicy;
use App\Policies\ClientPolicy;
use App\Policies\RentalBuildingPolicy;
use App\Policies\RentalModulePolicy;
use App\Policies\RentalUnitPolicy;
use App\Policies\SaleBuildingPolicy;
use App\Policies\SalesModulePolicy;
use App\Policies\SaleUnitPolicy;
use App\Policies\TenantPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(ChargeBatch::class, ChargeBatchPolicy::class);
        Gate::policy(RentalBuilding::class, RentalBuildingPolicy::class);
        Gate::policy(RentalUnit::class, RentalUnitPolicy::class);
        Gate::policy(Tenant::class, TenantPolicy::class);
        Gate::policy(RentCharge::class, RentalModulePolicy::class);
        Gate::policy(RentPayment::class, RentalModulePolicy::class);
        Gate::policy(TenantWaterBill::class, RentalModulePolicy::class);
        Gate::policy(TenantElectricityBill::class, RentalModulePolicy::class);
        Gate::policy(BuildingWaterUtilityBill::class, RentalModulePolicy::class);
        Gate::policy(BuildingElectricityBill::class, RentalModulePolicy::class);
        Gate::policy(RentalExpense::class, RentalModulePolicy::class);
        Gate::policy(Employee::class, RentalModulePolicy::class);
        Gate::policy(PayrollEntry::class, RentalModulePolicy::class);
        Gate::policy(Shareholder::class, RentalModulePolicy::class);
        Gate::policy(ShareholderBill::class, RentalModulePolicy::class);

        Gate::policy(SaleBuilding::class, SaleBuildingPolicy::class);
        Gate::policy(SaleUnit::class, SaleUnitPolicy::class);
        Gate::policy(Client::class, ClientPolicy::class);
        Gate::policy(SalesPayment::class, SalesModulePolicy::class);
        Gate::policy(SalesExpense::class, SalesModulePolicy::class);

        Gate::policy(User::class, UserPolicy::class);
    }
}
