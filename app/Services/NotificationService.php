<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Shipment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;

class NotificationService
{
    /** @param iterable<User> $users */
    public function notify(iterable $users, string $type, string $title, string $message, ?Shipment $shipment = null): void
    {
        $sent = [];

        foreach ($users as $user) {
            if (isset($sent[$user->id])) {
                continue;
            }
            $sent[$user->id] = true;

            UserNotification::query()->create([
                'user_id' => $user->id,
                'shipment_id' => $shipment?->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
            ]);
        }
    }

    /** @return Collection<int, User> */
    public function staff(): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Operator->value])
            ->where('is_active', true)
            ->get();
    }

    /** Staff plus the shipment's customer. */
    public function notifyStakeholders(Shipment $shipment, string $type, string $title, string $message): void
    {
        $recipients = $this->staff();

        $customer = $shipment->customer ?? User::query()->find($shipment->customer_id);
        if ($customer && $customer->is_active) {
            $recipients->push($customer);
        }

        $this->notify($recipients, $type, $title, $message, $shipment);
    }
}
