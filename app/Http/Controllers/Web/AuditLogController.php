<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['action' => ['nullable', 'string', 'max:60'], 'entity_type' => ['nullable', 'string', 'max:100']]);

        $logs = AuditLog::query()
            ->with('actor')
            ->when($request->filled('action'), fn ($q) => $q->where('action', 'like', '%'.$request->string('action')->trim().'%'))
            ->when($request->filled('entity_type'), fn ($q) => $q->where('entity_type', $request->string('entity_type')->toString()))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'entityTypes' => AuditLog::query()->distinct()->orderBy('entity_type')->pluck('entity_type'),
        ]);
    }
}
