<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TradeLaneResource;
use App\Models\TradeLane;

/** Read-only list of trade lanes (Groups A/B/C). They are managed on the Settings page by an administrator. */
class TradeLaneController extends Controller
{
    public function index()
    {
        return TradeLaneResource::collection(TradeLane::query()->orderBy('code')->get());
    }
}
