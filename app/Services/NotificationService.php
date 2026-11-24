<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Mail\ShipmentNotificationMail;
use App\Models\Shipment;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotificationService
{
    /** Notification types that are e-mailed to customers when NOTIFY_BY_EMAIL=true. */
    public const CUSTOMER_EMAIL_TYPES = ['shipment.created', 'shipment.status', 'shipment.delayed', 'exception.opened', 'exception.resolved'];

    /** Staff only get e-mails for events that need action (to avoid noise). */
    public const STAFF_EMAIL_TYPES = ['shipment.delayed', 'exception.opened'];

    /** @param iterable<User> $users */
    public function notify(iterable $users, string $type, string $title, string $message, ?Shipment $shipment = null): void
    {
        $sent = [];

        foreach ($users as $user) {
            if (isset($sent[$user->id])) {
                continue;
            }
            $sent[$user->id] = true;

            $record = UserNotification::query()->create([
                'user_id' => $user->id,
                'shipment_id' => $shipment?->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
            ]);

            $this->email($user, $record);
        }
    }

    /** Optional e-mail copy. A mail failure must never break the business operation. */
    private function email(User $user, UserNotification $record): void
    {
        if (! config('cargotrace.notifications.email') || blank($user->email)) {
            return;
        }

        $allowed = $user->isStaff() ? self::STAFF_EMAIL_TYPES : self::CUSTOMER_EMAIL_TYPES;
        if (! in_array($record->type, $allowed, true)) {
            return;
        }

        try {
            Mail::to($user->email)->send(new ShipmentNotificationMail($record));
        } catch (\Throwable $e) {
            Log::warning('Notification e-mail failed: '.$e->getMessage());
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
