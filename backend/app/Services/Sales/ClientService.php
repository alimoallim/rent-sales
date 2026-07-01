<?php

namespace App\Services\Sales;

use App\Enums\ClientStatus;
use App\Enums\SaleUnitStatus;
use App\Enums\SalesPaymentStatus;
use App\Models\Client;
use App\Models\SaleUnit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, User $actor): Client
    {
        return DB::transaction(function () use ($data, $actor): Client {
            $unit = SaleUnit::query()->lockForUpdate()->findOrFail($data['sale_unit_id']);

            if ($unit->status !== SaleUnitStatus::Available) {
                throw ValidationException::withMessages([
                    'sale_unit_id' => ['This unit is not available for sale.'],
                ]);
            }

            if ((int) $unit->sale_building_id !== (int) $data['sale_building_id']) {
                throw ValidationException::withMessages([
                    'sale_unit_id' => ['Unit does not belong to the selected building.'],
                ]);
            }

            $client = Client::query()->create([
                ...$data,
                'status' => ClientStatus::Active,
                'created_by' => $actor->id,
            ]);

            $unit->update(['status' => SaleUnitStatus::Sold]);

            return $client->load(['building', 'unit']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Client $client, array $data): Client
    {
        if ($client->status !== ClientStatus::Active) {
            throw ValidationException::withMessages([
                'client' => ['Only active clients can be updated.'],
            ]);
        }

        return DB::transaction(function () use ($client, $data): Client {
            $newUnitId = (int) $data['sale_unit_id'];
            $oldUnitId = (int) $client->sale_unit_id;

            if ($newUnitId !== $oldUnitId) {
                $newUnit = SaleUnit::query()->lockForUpdate()->findOrFail($newUnitId);

                if ((int) $newUnit->sale_building_id !== (int) $data['sale_building_id']) {
                    throw ValidationException::withMessages([
                        'sale_unit_id' => ['Unit does not belong to the selected building.'],
                    ]);
                }

                if ($newUnit->status !== SaleUnitStatus::Available) {
                    throw ValidationException::withMessages([
                        'sale_unit_id' => ['This unit is not available.'],
                    ]);
                }

                SaleUnit::query()->whereKey($oldUnitId)->update([
                    'status' => SaleUnitStatus::Available,
                ]);

                $newUnit->update(['status' => SaleUnitStatus::Sold]);
            }

            $client->update($data);

            return $client->fresh(['building', 'unit']);
        });
    }

    public function disable(Client $client, User $actor): Client
    {
        if ($client->status !== ClientStatus::Active) {
            throw ValidationException::withMessages([
                'client' => ['This client is already disabled.'],
            ]);
        }

        if ($client->payments()->where('status', SalesPaymentStatus::Active)->exists()) {
            throw ValidationException::withMessages([
                'client' => ['Cannot disable a client with active payments. Cancel payments first.'],
            ]);
        }

        return DB::transaction(function () use ($client): Client {
            SaleUnit::query()->whereKey($client->sale_unit_id)->update([
                'status' => SaleUnitStatus::Available,
            ]);

            $client->update(['status' => ClientStatus::Disabled]);

            return $client->fresh(['building', 'unit']);
        });
    }
}
