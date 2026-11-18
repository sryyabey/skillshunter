<?php

namespace Tests\Feature;

use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkillMarkdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_markdown_and_json_share_published_content_and_preserve_raw_download(): void
    {
        $instructions = "---\nname: example\n---\n\n# Instructions\n\nUse <example> & keep **formatting**.\n";
        $skill = Skill::factory()->published()->create([
            'source_url' => 'https://example.com/SKILL.md',
            'source_markdown' => $instructions,
            'editor_note' => 'Catalog note.',
            'use_cases' => ['Create a deck'],
            'limitations' => ['Requires local tools'],
            'compatibility' => [['model' => 'Example', 'success' => 90]],
            'sample_input' => 'Create slides.',
        ]);
        $json = $this->getJson(route('api.public.v1.skills.show', $skill->slug))->assertOk();
        $json->assertJsonPath('data.markdown_url', route('skills.markdown', $skill->slug))
            ->assertJsonPath('data.download_url', route('skills.download', $skill->slug));
        $this->get(route('skills.markdown', $skill->slug))->assertOk()
            ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeaderMissing('Content-Disposition')
            ->assertSee($json->json('data.title'), false)
            ->assertSee($json->json('data.summary'), false)
            ->assertSee('Catalog note.', false)->assertSee('Create a deck', false)
            ->assertSee('Requires local tools', false)->assertSee('"model":"Example"', false)
            ->assertSee($instructions, false)->assertDontSee('ai_review_details', false);
        $this->get(route('skills.download', $skill->slug))->assertOk()
            ->assertDownload('SKILL.md')->assertStreamedContent($instructions);
        $this->get(route('detail', $skill->slug))->assertOk()
            ->assertSee('rel="alternate" type="text/markdown"', false)
            ->assertSee(route('skills.markdown', $skill->slug), false)
            ->assertSee('rel="alternate" type="application/json"', false);
    }

    public function test_drafts_and_unknown_slugs_are_unavailable_in_all_formats(): void
    {
        $draft = Skill::factory()->create();
        foreach ([$draft->slug, 'missing-skill'] as $slug) {
            foreach (['skills.markdown', 'skills.download', 'detail', 'api.public.v1.skills.show'] as $route) {
                $this->get(route($route, $slug))->assertNotFound();
            }
        }
    }

    public function test_legacy_records_and_missing_optional_fields_are_supported(): void
    {
        $skill = Skill::factory()->published()->create(['instructions' => 'Legacy instructions.']);
        $this->get(route('skills.markdown', $skill->slug))->assertOk()
            ->assertSee('Legacy instructions.', false)->assertDontSee('### Compatibility', false);
        $this->get(route('skills.download', $skill->slug))->assertDownload($skill->slug.'.md')
            ->assertStreamedContent('Legacy instructions.');
    }
}
