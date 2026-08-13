<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceAdminTest extends TestCase
{
    use RefreshDatabase;

    private function servicePayload(array $overrides = []): array
    {
        return array_merge([
            'name_en' => 'Legal Consultation',
            'name_fr' => 'Consultation juridique',
            'name_ar' => 'استشارة قانونية',
            'description_en' => 'An hour of legal advice.',
            'description_fr' => 'Une heure de conseil juridique.',
            'description_ar' => 'ساعة من الاستشارة القانونية.',
            'price' => 500,
            'price_display_en' => 'from 3.000,00 MAD',
            'price_display_fr' => 'à partir de 3.000,00 MAD',
            'price_display_ar' => 'ابتداءً من 3.000,00 درهم',
            'notes_en' => 'Only by WhatsApp',
            'notes_fr' => 'Uniquement par WhatsApp',
            'notes_ar' => 'عبر واتساب فقط',
            'additional_notes_en' => 'Fee to the court',
            'additional_notes_fr' => 'Frais de tribunal',
            'additional_notes_ar' => 'رسوم المحكمة',
            'allows_office' => true,
            'allows_whatsapp' => true,
        ], $overrides);
    }

    public function test_admin_can_list_services(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Service::create($this->servicePayload());

        $this->actingAs($admin)
            ->withSession(['locale' => 'en'])
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('Legal Consultation')
            ->assertSee('New Service');
    }

    public function test_admin_can_create_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.services.store'), $this->servicePayload())
            ->assertRedirect(route('admin.services.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('services', [
            'name_en' => 'Legal Consultation',
            'price' => 500,
        ]);
    }

    public function test_admin_can_store_flexible_pricing_fields(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.services.store'), $this->servicePayload())
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('services', [
            'name_en' => 'Legal Consultation',
            'price_display_en' => 'from 3.000,00 MAD',
            'price_display_fr' => 'à partir de 3.000,00 MAD',
            'price_display_ar' => 'ابتداءً من 3.000,00 درهم',
            'notes_en' => 'Only by WhatsApp',
            'additional_notes_en' => 'Fee to the court',
        ]);
    }

    public function test_admin_can_store_consultation_options(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.services.store'), $this->servicePayload([
                'allows_office' => true,
                'allows_whatsapp' => false,
            ]))
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('services', [
            'name_en' => 'Legal Consultation',
            'allows_office' => 1,
            'allows_whatsapp' => 0,
        ]);
    }

    public function test_admin_service_list_shows_price_display_and_notes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Service::create($this->servicePayload());

        $this->actingAs($admin)
            ->withSession(['locale' => 'en'])
            ->get(route('admin.services.index'))
            ->assertOk()
            ->assertSee('from 3.000,00 MAD')
            ->assertSee('Only by WhatsApp')
            ->assertSee('Fee to the court')
            ->assertSee('Office')
            ->assertSee('WhatsApp');
    }

    public function test_admin_can_update_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::create($this->servicePayload());

        $this->actingAs($admin)
            ->put(route('admin.services.update', $service->id), $this->servicePayload([
                'name_en' => 'Updated Service',
                'price' => 600,
            ]))
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name_en' => 'Updated Service',
            'price' => 600,
        ]);
    }

    public function test_admin_can_delete_service(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $service = Service::create($this->servicePayload());

        $this->actingAs($admin)
            ->delete(route('admin.services.destroy', $service->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('services', ['id' => $service->id]);
    }

    public function test_service_requires_all_locale_names_and_price(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.services.store'), [
                'name_en' => 'Only English',
                'price' => 'abc',
            ])
            ->assertSessionHasErrors(['name_fr', 'name_ar', 'price']);

        $this->assertDatabaseCount('services', 0);
    }

    public function test_non_admin_cannot_access_services(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('admin.services.index'))
            ->assertForbidden();
    }
}
