<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TradeLaneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'route' => $this->route,
            'delay_low_max_hours' => $this->delay_low_max_hours,
            'delay_medium_max_hours' => $this->delay_medium_max_hours,
            'primary_exception' => $this->primary_exception->value,
            'kpi' => $this->kpi,
            'is_active' => $this->is_active,
        ];
    }
}
