<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\EtaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Admin only: delay thresholds (the "group parameter"). */
class SettingController extends Controller
{
    public function edit(EtaService $eta)
    {
        return view('settings.edit', ['thresholds' => $eta->thresholds()]);
    }

    public function update(Request $request, EtaService $eta, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'low_max_hours' => ['required', 'numeric', 'min:0', 'max:10000'],
            'medium_max_hours' => ['required', 'numeric', 'gt:low_max_hours', 'max:10000'],
        ]);

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

        return back()->with('status', 'Delay thresholds saved. Existing shipments are re-evaluated on their next update or by the hourly job.');
    }
}
