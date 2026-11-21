<?php

namespace App\Policies;

use App\Models\Shipment;
use App\Models\User;

/**
 * Authorization rules (enforced on the server for web pages AND API endpoints):
 *  - Admin: everything
 *  - Operator: all shipments, create/update (not terminal), add tracking and exceptions, no delete
 *  - Customer: read-only access to shipments that belong to them
 */
class ShipmentPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function view(User $user, Shipment $shipment): bool
    {
        return $user->isStaff() || $shipment->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, Shipment $shipment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isOperator() && ! $shipment->status->isTerminal();
    }

    public function delete(User $user, Shipment $shipment): bool
    {
        return $user->isAdmin();
    }

    public function addTracking(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }

    public function reportException(User $user, Shipment $shipment): bool
    {
        return $user->isStaff();
    }
}
