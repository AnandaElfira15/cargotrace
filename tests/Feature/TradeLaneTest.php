<?php

namespace Tests\Feature;

use App\Enums\ExceptionType;
use App\Enums\ShipmentStatus;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TradeLane;
use App\Models\User;
use App\Services\EtaService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Groups A, B and C live in one application as trade lanes with their own parameters. */
class TradeLaneTest extends TestCase
{
    use RefreshDatabase;

    private function lanes(): array
    {
        return [
            'A' => TradeLane::factory()->create(['code' => 'A', 'name' => 'Group A', 'delay_low_max_hours' => 6, 'delay_medium_max_hours' => 12, 'primary_exception' => ExceptionType::CustomsHold, 'kpi' => 'on_time_delivery_rate']),
            'B' => TradeLane::factory()->create(['code' => 'B', 'name' => 'Group B', 'delay_low_max_hours' => 9, 'delay_medium_max_hours' => 18, 'primary_exception' => ExceptionType::WeatherDelay, 'kpi' => 'average_delay_duration']),
            'C' => TradeLane::factory()->create(['code' => 'C', 'name' => 'Group C', 'delay_low_max_hours' => 12, 'delay_medium_max_hours' => 24, 'primary_exception' => ExceptionType::DocumentationIssue, 'kpi' => 'exception_resolution_time']),
        ];
    }

    private function lateShipment(?TradeLane $lane, float $lateHours, array $extra = []): Shipment
    {
        $expected = Carbon::parse('2026-06-10 12:00:00');

        return Shipment::factory()->create(array_merge([
            'trade_lane_id' => $lane?->id,
            'status' => ShipmentStatus::InTransit,
            'expected_departure_at' => $expected->copy()->subDays(10),
            'expected_arrival_at' => $expected,
            'estimated_arrival_at' => $expected->copy()->addMinutes((int) round($lateHours * 60)),
        ], $extra));
    }

    private function severity(Shipment $shipment): string
    {
        return app(EtaService::class)->calculate($shipment->refresh(), Carbon::parse('2026-06-01 00:00:00'))->severity->value;
    }

    // ------------------------------------------------------------ delay rules

    public function test_each_group_applies_its_own_delay_threshold(): void
    {
        $lanes = $this->lanes();

        // a 15 hour delay: A (limit 12) = HIGH, B (limit 18) = MEDIUM, C (limit 24) = MEDIUM, no lane (global 24) = MEDIUM
        $this->assertSame('HIGH', $this->severity($this->lateShipment($lanes['A'], 15)));
        $this->assertSame('MEDIUM', $this->severity($this->lateShipment($lanes['B'], 15)));
        $this->assertSame('MEDIUM', $this->severity($this->lateShipment($lanes['C'], 15)));
        $this->assertSame('MEDIUM', $this->severity($this->lateShipment(null, 15)));

        // a 20 hour delay: A = HIGH, B = HIGH, C = MEDIUM
        $this->assertSame('HIGH', $this->severity($this->lateShipment($lanes['A'], 20)));
        $this->assertSame('HIGH', $this->severity($this->lateShipment($lanes['B'], 20)));
        $this->assertSame('MEDIUM', $this->severity($this->lateShipment($lanes['C'], 20)));
    }

    public function test_the_low_limit_is_also_per_lane(): void
    {
        $lanes = $this->lanes();

        // 8 hours: A (LOW up to 6) = MEDIUM, B (LOW up to 9) = LOW
        $this->assertSame('MEDIUM', $this->severity($this->lateShipment($lanes['A'], 8)));
        $this->assertSame('LOW', $this->severity($this->lateShipment($lanes['B'], 8)));
    }

    public function test_a_lane_without_thresholds_falls_back_to_the_global_values(): void
    {
        $lane = TradeLane::factory()->create(['delay_low_max_hours' => null, 'delay_medium_max_hours' => null]);

        // global defaults in the test environment: 6 / 24
        $this->assertSame('MEDIUM', $this->severity($this->lateShipment($lane, 18)));
        $this->assertSame('HIGH', $this->severity($this->lateShipment($lane, 30)));
    }

    public function test_the_explanation_names_the_rule_set_that_was_used(): void
    {
        $lanes = $this->lanes();
        $now = Carbon::parse('2026-06-01 00:00:00');

        $withLane = app(EtaService::class)->calculate($this->lateShipment($lanes['A'], 10), $now)->explanation();
        $withoutLane = app(EtaService::class)->calculate($this->lateShipment(null, 10), $now)->explanation();

        $this->assertStringContainsString('Rules (Group A lane)', $withLane);
        $this->assertStringContainsString('above 12 h = HIGH', $withLane);
        $this->assertStringContainsString('Rules (global thresholds)', $withoutLane);
    }

    public function test_changing_the_lane_of_a_shipment_re_evaluates_the_delay(): void
    {
        $lanes = $this->lanes();
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create([
            'trade_lane_id' => $lanes['C']->id,
            'estimated_arrival_at' => null,
        ]);
        $shipment->update(['estimated_arrival_at' => $shipment->expected_arrival_at->copy()->addHours(15)]);

        Sanctum::actingAs($operator);
        $this->patchJson("/api/shipments/{$shipment->id}", ['trade_lane_id' => $lanes['C']->id])
            ->assertOk()->assertJsonPath('data.delay.severity', 'MEDIUM'); // C: limit 24 h

        $this->patchJson("/api/shipments/{$shipment->id}", ['trade_lane_id' => $lanes['A']->id])
            ->assertOk()->assertJsonPath('data.delay.severity', 'HIGH')    // A: limit 12 h
            ->assertJsonPath('data.trade_lane.code', 'A');
    }

    // -------------------------------------------------------------------- API

    public function test_api_lists_the_trade_lanes(): void
    {
        $this->lanes();
        Sanctum::actingAs(User::factory()->customer()->create());

        $this->getJson('/api/trade-lanes')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.code', 'A')
            ->assertJsonPath('data.1.delay_medium_max_hours', 18)
            ->assertJsonPath('data.2.primary_exception', 'DOCUMENTATION_ISSUE');

    }

    public function test_a_shipment_can_be_created_with_a_lane_and_filtered_by_it(): void
    {
        $lanes = $this->lanes();
        Sanctum::actingAs(User::factory()->operator()->create());

        $payload = [
            'customer_id' => User::factory()->customer()->create()->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'trade_lane_id' => $lanes['B']->id,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
            'cargo_type' => 'Toys',
            'weight_kg' => 100,
            'volume_cbm' => 2,
            'expected_departure_at' => now()->addDay()->toIso8601String(),
            'expected_arrival_at' => now()->addDays(20)->toIso8601String(),
        ];

        $this->postJson('/api/shipments', $payload)->assertCreated()
            ->assertJsonPath('data.trade_lane.code', 'B')
            ->assertJsonPath('data.trade_lane.route', $lanes['B']->route);

        Shipment::factory()->create(['trade_lane_id' => $lanes['A']->id]);

        $this->getJson('/api/shipments?trade_lane_id='.$lanes['B']->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments?trade_lane_id='.$lanes['A']->id)->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/shipments')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_an_unknown_or_inactive_lane_is_rejected(): void
    {
        $inactive = TradeLane::factory()->create(['is_active' => false]);
        Sanctum::actingAs(User::factory()->operator()->create());

        $base = [
            'customer_id' => User::factory()->customer()->create()->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
            'cargo_type' => 'Toys', 'weight_kg' => 1, 'volume_cbm' => 1,
            'expected_departure_at' => now()->addDay()->toIso8601String(),
            'expected_arrival_at' => now()->addDays(5)->toIso8601String(),
        ];

        $this->postJson('/api/shipments', $base + ['trade_lane_id' => 9999])->assertStatus(422)->assertJsonValidationErrors('trade_lane_id');
        $this->postJson('/api/shipments', $base + ['trade_lane_id' => $inactive->id])->assertStatus(422)->assertJsonValidationErrors('trade_lane_id');
    }

    // -------------------------------------------------------------- dashboard

    public function test_the_dashboard_breaks_the_numbers_down_per_lane_and_can_filter_by_lane(): void
    {
        $lanes = $this->lanes();

        Shipment::factory()->status(ShipmentStatus::Delivered)->create(['trade_lane_id' => $lanes['A']->id, 'is_delayed' => false]);
        Shipment::factory()->status(ShipmentStatus::Delivered)->create(['trade_lane_id' => $lanes['A']->id, 'is_delayed' => true, 'delay_hours' => 8, 'delay_severity' => 'MEDIUM']);
        $inTransit = Shipment::factory()->status(ShipmentStatus::InTransit)->create(['trade_lane_id' => $lanes['B']->id, 'is_delayed' => true, 'delay_hours' => 20, 'delay_severity' => 'HIGH']);
        ShipmentException::factory()->create(['shipment_id' => $inTransit->id, 'type' => ExceptionType::WeatherDelay]);

        $admin = User::factory()->admin()->create();

        $all = $this->actingAs($admin)->getJson('/dashboard/data')->assertOk();
        $all->assertJsonPath('totals.shipments', 3)
            ->assertJsonCount(3, 'lanes')
            ->assertJsonPath('lanes.0.code', 'A')
            ->assertJsonPath('lanes.0.shipments', 2)
            ->assertJsonPath('lanes.0.threshold_hours', 12)
            ->assertJsonPath('lanes.0.kpis.on_time_delivery_rate', 50)
            ->assertJsonPath('lanes.1.code', 'B')
            ->assertJsonPath('lanes.1.delayed', 1)
            ->assertJsonPath('lanes.1.open_exceptions', 1)
            ->assertJsonPath('lanes.1.primary_exception_count', 1)
            ->assertJsonPath('lanes.1.kpi', 'average_delay_duration')
            ->assertJsonPath('lanes.2.shipments', 0);

        $this->actingAs($admin)->getJson('/dashboard/data?lane='.$lanes['B']->id)->assertOk()
            ->assertJsonPath('totals.shipments', 1)
            ->assertJsonPath('totals.open_exceptions', 1)
            ->assertJsonPath('primary_kpi', 'average_delay_duration')
            ->assertJsonPath('selected_lane.code', 'B')
            ->assertJsonCount(3, 'lanes'); // the breakdown always lists every lane

        $this->actingAs($admin)->getJson('/dashboard/data?lane=9999')->assertStatus(422);
    }

    public function test_a_customer_only_sees_their_own_numbers_in_the_lane_breakdown(): void
    {
        $lanes = $this->lanes();
        $customer = User::factory()->customer()->create();
        Shipment::factory()->count(2)->create(['trade_lane_id' => $lanes['A']->id, 'customer_id' => $customer->id]);
        Shipment::factory()->count(5)->create(['trade_lane_id' => $lanes['A']->id]);

        $this->actingAs($customer)->getJson('/dashboard/data')->assertOk()
            ->assertJsonPath('lanes.0.shipments', 2);
    }

    public function test_the_dashboard_page_shows_the_lane_table_and_the_lane_filter(): void
    {
        $lanes = $this->lanes();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertSee('Trade lane performance')
            ->assertSee('Group B')
            ->assertSee('Weather Delay');

        $this->actingAs($admin)->get('/dashboard?lane='.$lanes['C']->id)->assertOk()->assertSee('Group C');
    }

    // ------------------------------------------------------------- web pages

    public function test_the_shipment_list_can_be_filtered_by_lane_and_shows_the_lane_code(): void
    {
        $lanes = $this->lanes();
        Shipment::factory()->create(['trade_lane_id' => $lanes['A']->id, 'shipment_number' => 'CT-LANE-A']);
        Shipment::factory()->create(['trade_lane_id' => $lanes['B']->id, 'shipment_number' => 'CT-LANE-B']);

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/shipments?trade_lane_id='.$lanes['A']->id)
            ->assertOk()->assertSee('CT-LANE-A')->assertDontSee('CT-LANE-B');
    }

    public function test_the_shipment_page_and_form_show_the_lane(): void
    {
        $lanes = $this->lanes();
        $shipment = Shipment::factory()->create(['trade_lane_id' => $lanes['C']->id]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('shipments.show', $shipment))->assertOk()->assertSee('Group C');
        $this->actingAs($admin)->get(route('shipments.edit', $shipment))->assertOk()->assertSee('Trade lane (group scenario)');
        $this->actingAs($admin)->get(route('shipments.create'))->assertOk()->assertSee('Group A')->assertSee('Group B');
    }

    public function test_the_exception_form_preselects_the_main_exception_of_the_lane(): void
    {
        $lanes = $this->lanes();
        $shipment = Shipment::factory()->status(ShipmentStatus::InTransit)->create(['trade_lane_id' => $lanes['B']->id]);

        $this->actingAs(User::factory()->operator()->create())->get(route('shipments.show', $shipment))
            ->assertOk()->assertSee('value="WEATHER_DELAY" selected', false);
    }

    // -------------------------------------------------------------- settings

    public function test_admin_edits_the_parameters_of_each_lane(): void
    {
        $lanes = $this->lanes();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('settings.update'), [
            'low_max_hours' => 6,
            'medium_max_hours' => 24,
            'lanes' => [
                $lanes['A']->id => ['delay_low_max_hours' => 5, 'delay_medium_max_hours' => 15, 'primary_exception' => 'PORT_CONGESTION', 'kpi' => 'average_delay_duration'],
                $lanes['B']->id => ['delay_low_max_hours' => '', 'delay_medium_max_hours' => '', 'primary_exception' => 'WEATHER_DELAY', 'kpi' => 'average_delay_duration'],
            ],
        ])->assertSessionHasNoErrors();

        $a = $lanes['A']->refresh();
        $this->assertSame(15.0, $a->delay_medium_max_hours);
        $this->assertSame(ExceptionType::PortCongestion, $a->primary_exception);
        $this->assertSame('average_delay_duration', $a->kpi);

        $b = $lanes['B']->refresh();
        $this->assertNull($b->delay_medium_max_hours); // empty = use the global value

        $this->assertDatabaseHas('audit_logs', ['action' => 'trade_lane.updated', 'entity_type' => 'TradeLane']);
        $this->actingAs($admin)->get(route('settings.edit'))->assertOk()->assertSee('Trade lanes (Groups A, B and C)');
    }

    public function test_lane_settings_are_validated_and_admin_only(): void
    {
        $lanes = $this->lanes();
        $admin = User::factory()->admin()->create();
        $id = $lanes['A']->id;

        $this->actingAs($admin)->from(route('settings.edit'))->put(route('settings.update'), [
            'low_max_hours' => 6, 'medium_max_hours' => 24,
            'lanes' => [$id => ['delay_low_max_hours' => 10, 'delay_medium_max_hours' => 4, 'primary_exception' => 'CUSTOMS_HOLD', 'kpi' => 'on_time_delivery_rate']],
        ])->assertSessionHasErrors("lanes.{$id}.delay_medium_max_hours");

        $this->actingAs($admin)->put(route('settings.update'), [
            'low_max_hours' => 6, 'medium_max_hours' => 24,
            'lanes' => [$id => ['primary_exception' => 'UFO', 'kpi' => 'nonsense']],
        ])->assertSessionHasErrors(["lanes.{$id}.primary_exception", "lanes.{$id}.kpi"]);

        $this->actingAs(User::factory()->operator()->create())->put(route('settings.update'), ['low_max_hours' => 1, 'medium_max_hours' => 2])->assertForbidden();
    }

    // ---------------------------------------------------------------- seeder

    public function test_the_demo_seeder_builds_groups_a_b_and_c_together(): void
    {
        $this->seed(DemoDataSeeder::class);

        $this->assertSame(['A', 'B', 'C'], TradeLane::query()->orderBy('code')->pluck('code')->all());
        $this->assertSame(66, Shipment::query()->count());
        $this->assertSame(15, ShipmentException::query()->count());

        $expected = ['A' => [12.0, 'CUSTOMS_HOLD'], 'B' => [18.0, 'WEATHER_DELAY'], 'C' => [24.0, 'DOCUMENTATION_ISSUE']];
        foreach (TradeLane::query()->get() as $lane) {
            $this->assertSame(22, $lane->shipments()->count(), "lane {$lane->code} shipments");
            $this->assertGreaterThan(0, $lane->shipments()->where('is_delayed', true)->count(), "lane {$lane->code} has delayed shipments");
            $this->assertSame($expected[$lane->code][0], $lane->delay_medium_max_hours);
            $this->assertSame($expected[$lane->code][1], $lane->primary_exception->value);
        }

        // ids relied upon by the docs and the Postman collection
        $this->assertSame('admin@cargotrace.test', User::query()->find(1)->email);
        $this->assertSame('customer@cargotrace.test', User::query()->find(3)->email);

        // seeding again must not duplicate anything
        $this->seed(DemoDataSeeder::class);
        $this->assertSame(66, Shipment::query()->count());
        $this->assertSame(3, TradeLane::query()->count());
    }
}
