<?php

namespace Tests\Feature;

use App\Models\LegalAidRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaginationCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_advisor_cases_pagination_renders_with_enough_cases(): void
    {
        $advisor = User::factory()->create(['role' => 'advisor']);

        for ($i = 1; $i <= 12; $i++) {
            LegalAidRequest::create([
                'ticket_number' => '6000'.$i,
                'full_name' => 'Client '.$i,
                'email' => 'client'.$i.'@example.com',
                'phone' => '+212600000000',
                'case_description' => 'Case '.$i,
                'status' => LegalAidRequest::STATUS_PAID,
                'paid_at' => now(),
                'base_price' => 500,
            ]);
        }

        $this->actingAs($advisor)
            ->get(route('advisor.cases.index'))
            ->assertOk()
            ->assertSee('?page=2', false)
            ->assertSee('600010');
    }
}
