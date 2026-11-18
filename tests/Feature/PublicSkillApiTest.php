<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSkillApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_is_public_filtered_paginated_and_omits_full_instructions(): void
    {
        $category = Category::factory()->create();
        $skill = Skill::factory()->for($category)->published()->create(['title' => 'Create presentation']);
        Skill::factory()->for($category)->create(['title' => 'Create presentation draft']);
        Skill::factory()->published()->create(['title' => 'Unrelated item']);

        $this->getJson('/api/public/v1/skills?'.http_build_query(['q' => 'presentation', 'category' => $category->slug, 'per_page' => 1]))
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.slug', $skill->slug)
            ->assertJsonPath('data.0.json_url', route('api.public.v1.skills.show', $skill->slug))
            ->assertJsonPath('data.0.markdown_url', route('skills.markdown', $skill->slug))
            ->assertJsonMissingPath('data.0.instructions')->assertJsonMissingPath('data.0.id');

        $this->getJson('/api/public/v1/skills?per_page=1')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->getJson('/api/public/v1/skills?q=nonexistentterm')->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_details_only_expose_published_skills_and_categories(): void
    {
        $skill = Skill::factory()->published()->create();
        $draft = Skill::factory()->create();
        $this->getJson('/api/public/v1/skills/'.$skill->slug)->assertOk()
            ->assertJsonPath('data.instructions', $skill->instructions)
            ->assertJsonMissingPath('data.approved_by')->assertJsonMissingPath('data.ai_review_details');
        $this->get('/api/public/v1/skills/'.$draft->slug)->assertNotFound()->assertHeader('Content-Type', 'application/json');
        $this->getJson('/api/public/v1/skills/missing')->assertNotFound();
        $this->getJson('/api/public/v1/categories')->assertOk()->assertExactJson([
            'data' => [['slug' => $skill->category->slug, 'name' => $skill->category->name]],
        ]);
    }

    public function test_invalid_filters_return_json_validation_errors(): void
    {
        foreach (['per_page' => 101, 'page' => 0, 'q' => str_repeat('x', 101), 'category' => ['invalid']] as $field => $value) {
            $this->get('/api/public/v1/skills?'.http_build_query([$field => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_cross_origin_reads_work_without_credentials_and_writes_are_unavailable(): void
    {
        $this->withHeader('Origin', 'https://agent.example')->getJson('/api/public/v1/skills')
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeaderMissing('Access-Control-Allow-Credentials');
        $this->options('/api/public/v1/skills', [], [
            'Origin' => 'https://agent.example',
            'Access-Control-Request-Method' => 'GET',
        ])->assertNoContent()->assertHeader('Access-Control-Allow-Methods', 'GET, HEAD, OPTIONS');
        $this->postJson('/api/public/v1/skills', [])->assertStatus(405);
        $this->putJson('/api/public/v1/skills/example', [])->assertStatus(405);
        $this->deleteJson('/api/public/v1/skills/example')->assertStatus(405);
        $this->getJson('/api/v1/skills')->assertUnauthorized();
    }

    public function test_public_endpoints_share_an_ip_rate_limit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->getJson('/api/public/v1/categories')->assertOk();
        }
        $this->getJson('/api/public/v1/skills')->assertStatus(429)->assertHeader('Retry-After');
    }
}
