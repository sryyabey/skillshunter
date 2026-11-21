<?php

namespace Tests\Feature;

use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillComparisonTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_compare_published_skills_in_requested_order(): void
    {
        $first = Skill::factory()->published()->create(['title' => 'First comparator', 'compatibility' => ['Claude Code']]);
        $second = Skill::factory()->published()->create(['title' => 'Second comparator', 'setup_tip' => null]);
        $draft = Skill::factory()->create(['title' => 'Private comparator']);

        $this->get(route('skills.compare', ['skills' => [$second->slug, $draft->slug, $first->slug]]))
            ->assertOk()->assertSeeInOrder(['Second comparator', 'First comparator'])
            ->assertDontSee('Private comparator')->assertSee('no longer available')
            ->assertSee('Claude Code')->assertSee('Not specified')
            ->assertSee('data-compare-focus="0"', false)->assertSee('noindex,follow');
    }

    public function test_selection_is_limited_and_validated(): void
    {
        $this->getJson(route('skills.compare', ['skills' => ['one', 'two', 'three', 'four']]))->assertUnprocessable();
        $this->getJson(route('skills.compare', ['skills' => ['one', 'one']]))->assertUnprocessable();
        $this->getJson(route('skills.compare', ['skills' => 'one']))->assertUnprocessable();
    }

    public function test_empty_and_single_selections_show_a_directory_link(): void
    {
        $skill = Skill::factory()->published()->create();
        $this->get(route('skills.compare'))->assertOk()->assertSee('Select at least two');
        $this->get(route('skills.compare', ['skills' => [$skill->slug]]))->assertOk()->assertSee('Select at least two');
    }

    public function test_comparison_controls_are_available_on_catalog_and_detail_pages(): void
    {
        $skill = Skill::factory()->published()->create();
        foreach ([route('welcome'), route('detail', $skill->slug), route('welcome', ['_catalog_results' => 1])] as $url) {
            $this->get($url)->assertOk()->assertSee('data-compare-toggle', false)->assertSee('data-slug="'.$skill->slug.'"', false);
        }
    }
}
