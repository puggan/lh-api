<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * POST /api/terms
 *
 * Runs against the configured database inside a transaction that is rolled back after each test.
 */
class TermsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_terms_become_the_last_terms(): void
    {
        $response = $this->postJson('/api/terms', ['content' => 'Nya villkor'])
            ->assertCreated()
            ->assertJson(['content' => 'Nya villkor']);

        $this->getJson('/api/terms/last')
            ->assertOk()
            ->assertJson(['id' => $response->json('id'), 'content' => 'Nya villkor']);
        $this->assertDatabaseHas('logs', ['table_name' => 'terms', 'row_id' => $response->json('id'), 'action' => 'created']);
    }

    public function test_empty_content_is_422(): void
    {
        $this->postJson('/api/terms', ['content' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }
}
