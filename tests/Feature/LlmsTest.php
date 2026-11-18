<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LlmsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guide_is_public_and_describes_the_existing_access_paths(): void
    {
        $this->get('/llms.txt')->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertSee('# SkillsHunter', false)
            ->assertSee(route('api.public.v1.skills.index'), false)
            ->assertSee(route('api.public.v1.categories.index'), false)
            ->assertSee(route('skills.markdown', 'SKILL_SLUG'), false)
            ->assertSee(route('skills.download', 'SKILL_SLUG'), false)
            ->assertSee('listing_approved_at', false)
            ->assertSee('Retry-After', false)
            ->assertSee('No categories currently contain published skills.', false);
    }

    public function test_categories_reflect_publication_changes_without_exposing_drafts_or_full_instructions(): void
    {
        $public = Category::factory()->create(['name' => 'Public Category']);
        $private = Category::factory()->create(['name' => 'Private Category']);
        $skill = Skill::factory()->for($public)->published()->create(['instructions' => 'Do not inline this entire skill.']);
        Skill::factory()->for($private)->create();

        $this->get('/llms.txt')->assertOk()->assertSee('Public Category', false)
            ->assertSee(route('api.public.v1.skills.index', ['category' => $public->slug]), false)
            ->assertDontSee('Private Category', false)
            ->assertDontSee($skill->instructions, false);

        $skill->update(['is_published' => false]);
        $this->get('/llms.txt')->assertOk()->assertDontSee('Public Category', false);
    }
}
