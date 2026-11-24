<?php

namespace App\Http\Controllers\Web;

use App\Enums\ExceptionType;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TradeLane;
use App\Services\AuditService;
use App\Services\EtaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Admin only: global delay thresholds plus the parameters of each trade lane (Group A/B/C). */
class SettingController extends Controller
{
    public function edit(EtaService $eta)
    {
        return view('settings.edit', [
            'thresholds' => $eta->thresholds(),
            'lanes' => TradeLane::query()->orderBy('code')->get(),
            'exceptionTypes' => ExceptionType::cases(),
            'kpis' => TradeLane::KPIS,
        ]);
    }

    public function update(Request $request, EtaService $eta, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'low_max_hours' => ['required', 'numeric', 'min:0', 'max:10000'],
            'medium_max_hours' => ['required', 'numeric', 'gt:low_max_hours', 'max:10000'],
            'lanes' => ['sometimes', 'array'],
            'lanes.*.delay_low_max_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'lanes.*.delay_medium_max_hours' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'lanes.*.primary_exception' => ['required', Rule::in(ExceptionType::values())],
            'lanes.*.kpi' => ['required', Rule::in(array_keys(TradeLane::KPIS))],
        ]);

        $laneInput = $data['lanes'] ?? [];

        // A lane's MEDIUM limit must be above its LOW limit when both are given.
        $errors = [];
        foreach ($laneInput as $id => $values) {
            $low = $values['delay_low_max_hours'] ?? null;
            $medium = $values['delay_medium_max_hours'] ?? null;
            if ($low !== null && $medium !== null && (float) $medium <= (float) $low) {
                $errors["lanes.{$id}.delay_medium_max_hours"] = 'The MEDIUM limit must be greater than the LOW limit.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $old = $eta->thresholds();
        foreach (['delay.low_max_hours' => $data['low_max_hours'], 'delay.medium_max_hours' => $data['medium_max_hours']] as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }
        $audit->log(
            'settings.updated',
            Setting::query()->findOrFail('delay.low_max_hours'),
            $old,
            ['low_max' => (float) $data['low_max_hours'], 'medium_max' => (float) $data['medium_max_hours']],
            $request->user()
        );

        foreach (TradeLane::query()->whereIn('id', array_keys($laneInput))->get() as $lane) {
            $values = $laneInput[$lane->id];
            $before = $lane->only(['delay_low_max_hours', 'delay_medium_max_hours', 'primary_exception', 'kpi']);
            $lane->fill([
                'delay_low_max_hours' => $values['delay_low_max_hours'] ?? null,
                'delay_medium_max_hours' => $values['delay_medium_max_hours'] ?? null,
                'primary_exception' => $values['primary_exception'],
                'kpi' => $values['kpi'],
            ])->save();

            if ($lane->wasChanged()) {
                $after = $lane->only(['delay_low_max_hours', 'delay_medium_max_hours', 'primary_exception', 'kpi']);
                $audit->log(
                    'trade_lane.updated',
                    $lane,
                    array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $before),
                    array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $after),
                    $request->user()
                );
            }
        }

        return back()->with('status', 'Settings saved. Existing shipments are re-evaluated on their next update or by the hourly job (php artisan cargotrace:evaluate-delays).');
    }
}
