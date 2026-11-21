<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditService
{
    /**
     * Record who did what, when, and to which entity. Never pass passwords or tokens in $old/$new.
     */
    public function log(string $action, Model $entity, ?array $old = null, ?array $new = null, ?User $actor = null): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'action' => $action,
            'entity_type' => class_basename($entity),
            'entity_id' => is_numeric($entity->getKey()) ? (int) $entity->getKey() : null,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => request()?->ip(),
        ]);
    }
}
