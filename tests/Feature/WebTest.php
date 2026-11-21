<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/shipments')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Sign in');
    }

    public function test_user_can_login_and_logout(): void
    {
        User::factory()->admin()->create(['email' => 'admin@example.com']);

        $this->post('/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_invalid_login_shows_an_error(): void
    {
        User::factory()->create(['email' => 'user@example.com']);

        $this->from('/login')->post('/login', ['email' => 'user@example.com', 'password' => 'nope'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_customer_can_self_register_as_customer_only(): void
    {
        $this->post('/register', [
            'name' => 'New Customer', 'email' => 'cust@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'admin',
        ])->assertRedirect('/dashboard');

        $this->assertDatabaseHas('users', ['email' => 'cust@example.com', 'role' => 'customer']);
    }

    public function test_every_role_can_open_the_dashboard(): void
    {
        foreach (['admin', 'operator', 'customer'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create())
                ->get('/dashboard')->assertOk()->assertSee('Operations dashboard');
        }
    }

    public function test_role_based_access_is_enforced_on_the_server(): void
    {
        $customer = User::factory()->customer()->create();
        $operator = User::factory()->operator()->create();

        foreach (['/carriers', '/locations', '/users', '/audit-logs', '/settings', '/exceptions', '/shipments/create'] as $url) {
            $this->actingAs($customer)->get($url)->assertForbidden();
        }

        foreach (['/carriers', '/locations', '/users', '/audit-logs', '/settings'] as $url) {
            $this->actingAs($operator)->get($url)->assertForbidden();
        }

        $this->actingAs($operator)->get('/exceptions')->assertOk();
        $this->actingAs($operator)->get('/shipments/create')->assertOk();
    }

    public function test_admin_pages_render(): void
    {
        $admin = User::factory()->admin()->create();
        Carrier::factory()->create();
        Location::factory()->create();

        foreach (['/carriers', '/carriers/create', '/locations', '/locations/create', '/users', '/users/create', '/audit-logs', '/settings', '/notifications'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_operator_creates_a_shipment_through_the_web_form_and_views_it(): void
    {
        $operator = User::factory()->operator()->create();
        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($operator)->post('/shipments', [
            'customer_id' => $customer->id,
            'carrier_id' => Carrier::factory()->create()->id,
            'origin_location_id' => Location::factory()->create()->id,
            'destination_location_id' => Location::factory()->create()->id,
            'cargo_type' => 'Textiles',
            'weight_kg' => 900,
            'volume_cbm' => 8,
            'expected_departure_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'expected_arrival_at' => now()->addDays(12)->format('Y-m-d\TH:i'),
            'items' => [
                ['description' => 'Cotton rolls', 'quantity' => 5],
                ['description' => '', 'quantity' => 1], // blank repeater row is ignored
            ],
        ]);

        $shipment = Shipment::query()->firstOrFail();
        $response->assertRedirect(route('shipments.show', $shipment));
        $this->assertCount(1, $shipment->items);

        $this->actingAs($operator)->get(route('shipments.show', $shipment))
            ->assertOk()->assertSee($shipment->shipment_number)->assertSee('Tracking timeline');
        $this->actingAs($customer)->get(route('shipments.show', $shipment))->assertOk();
        $this->actingAs($operator)->get(route('shipments.edit', $shipment))->assertOk();
        $this->actingAs($operator)->get('/shipments?q='.$shipment->shipment_number.'&status=CREATED')->assertOk()->assertSee($shipment->shipment_number);
    }

    public function test_customer_cannot_open_another_customers_shipment_page(): void
    {
        $shipment = Shipment::factory()->create();

        $this->actingAs(User::factory()->customer()->create())->get(route('shipments.show', $shipment))->assertForbidden();
    }

    public function test_invalid_status_transition_from_the_web_form_shows_a_validation_error(): void
    {
        $operator = User::factory()->operator()->create();
        $shipment = Shipment::factory()->create();

        $this->actingAs($operator)->from(route('shipments.show', $shipment))
            ->post(route('shipments.tracking.store', $shipment), [
                'event_type' => 'STATUS_UPDATE', 'status' => 'DELIVERED', 'occurred_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ])->assertRedirect(route('shipments.show', $shipment))->assertSessionHasErrors('status');

        $this->assertDatabaseCount('tracking_events', 0);
    }

    public function test_notifications_are_listed_and_can_be_marked_read(): void
    {
        $customer = User::factory()->customer()->create();
        $shipment = Shipment::factory()->create(['customer_id' => $customer->id]);
        app(NotificationService::class)->notify([$customer], 'test', 'Hello there', 'Something happened.', $shipment);

        $this->assertSame(1, $customer->unreadNotificationCount());
        $this->actingAs($customer)->get('/notifications')->assertOk()->assertSee('Hello there');

        $this->actingAs($customer)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $customer->unreadNotificationCount());
    }

    public function test_user_cannot_mark_someone_elses_notification_as_read(): void
    {
        $owner = User::factory()->customer()->create();
        app(NotificationService::class)->notify([$owner], 'test', 'Private', 'Not yours.');
        $notification = $owner->inbox()->firstOrFail();

        $this->actingAs(User::factory()->customer()->create())
            ->post("/notifications/{$notification->id}/read")
            ->assertForbidden();
    }
}
