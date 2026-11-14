<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SkillApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'skill-integration-test-token-at-least-32-characters';

    protected function setUp(): void
    {
        parent::setUp();

        config(['skill-integration.token' => self::TOKEN]);
    }

    public function test_api_requires_a_configured_bearer_token(): void
    {
        $this->get('/api/v1/skills')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
        $this->withToken('invalid-token')->getJson('/api/v1/skill-categories')->assertUnauthorized();

        config(['skill-integration.token' => null]);

        $this->withToken(self::TOKEN)->getJson('/api/v1/skills')->assertUnauthorized();
    }

    public function test_put_creates_and_updates_one_draft_by_slug(): void
    {
        $category = Category::factory()->create();
        $path = '/api/v1/skills/integration-example';
        $payload = [
            'category_slug' => $category->slug,
            'title' => 'Integration Example',
            'summary' => 'Summarizes a set of documents.',
            'instructions' => 'List each claim with its source.',
            'source_url' => 'https://example.com/skills/example',
            'compatibility' => [['model' => 'Example model', 'success' => 90]],
        ];

        $this->withToken(self::TOKEN)->putJson($path, $payload)
            ->assertCreated()
            ->assertHeader('Location', url($path))
            ->assertJsonPath('data.slug', 'integration-example')
            ->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.source_url', $payload['source_url']);

        unset($payload['source_url']);
        $this->putJson($path, [...$payload, 'title' => 'Updated Example'])
            ->assertOk()->assertJsonPath('data.title', 'Updated Example');

        $this->assertDatabaseCount('skills', 1);
        $this->getJson($path)->assertOk()->assertJsonPath('data.source_url', 'https://example.com/skills/example');
        $this->get('/skills/integration-example')->assertNotFound();
    }

    public function test_source_ingestion_preserves_markdown_and_replaces_it_when_the_link_changes(): void
    {
        $category = Category::factory()->create();
        $path = '/api/v1/skills/source-example';
        $markdown = "\n---\nname: demo\n---\n\nOriginal instructions.\n";
        $payload = [
            'category_slug' => $category->slug,
            'title' => 'Source Example',
            'summary' => 'Demonstrates source ingestion.',
            'source_url' => 'https://github.com/example/project/blob/main/SKILL.md',
        ];

        $this->withToken(self::TOKEN)->putJson($path, [...$payload, 'source_markdown' => $markdown])
            ->assertCreated()->assertJsonPath('data.instructions', $markdown);

        $this->putJson($path, [
            ...$payload,
            'source_url' => 'https://example.com/replacement',
            'instructions' => 'Replacement instructions.',
        ])->assertOk()->assertJsonPath('data.instructions', 'Replacement instructions.');
        $this->assertNull(Skill::where('slug', 'source-example')->sole()->source_markdown);
    }

    public function test_worker_requires_a_pinned_source_and_only_an_editor_can_publish_it(): void
    {
        $category = Category::factory()->create();
        $commit = str_repeat('a', 40);
        $path = '/api/v1/skills/github-example';
        $payload = [
            'category_slug' => $category->slug,
            'title' => 'Example',
            'summary' => 'Example summary.',
            'source_markdown' => "Original instructions.\n",
            'source_url' => "https://github.com/example/project/blob/{$commit}/SKILL.md",
        ];

        $this->withToken(self::TOKEN)->putJson($path, $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('source_commit');
        $this->putJson($path, [...$payload, 'source_commit' => str_repeat('b', 40)])
            ->assertUnprocessable()->assertJsonValidationErrors('source_url');
        $this->putJson($path, [...$payload, 'source_commit' => $commit])->assertCreated();

        $skill = Skill::where('slug', 'github-example')->sole();
        try {
            $skill->update(['is_published' => true]);
            $this->fail('An unapproved worker draft became public.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_published', $exception->errors());
        }

        $editor = User::factory()->create();
        $this->actingAs($editor);
        $skill->refresh()->update(['verified_at' => today(), 'is_published' => true]);
        $this->assertSame($editor->id, $skill->approved_by);
        $skill->update(['source_markdown' => 'Updated original instructions.']);
        $this->assertFalse($skill->refresh()->is_published);
        $this->assertNull($skill->approved_by);
        $this->assertNull($skill->verified_at);
    }

    public function test_ingestion_rejects_invalid_content_and_editorial_fields(): void
    {
        $this->withToken(self::TOKEN)->putJson('/api/v1/skills/Invalid_Slug', [
            'category_slug' => 'unknown',
            'title' => 'Example',
            'summary' => 'Example summary.',
            'instructions' => 'Example instructions.',
            'source_url' => 'file:///tmp/source',
            'is_published' => true,
            'verified_at' => null,
            'compatibility' => [['model' => 'Example model', 'success' => 110]],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug', 'category_slug', 'source_url', 'is_published', 'verified_at', 'compatibility.0.success']);

        $this->assertDatabaseCount('skills', 0);
    }

    public function test_ingestion_cannot_overwrite_published_content(): void
    {
        $skill = Skill::factory()->published()->create();

        $this->withToken(self::TOKEN)->putJson('/api/v1/skills/'.$skill->slug, [
            'category_slug' => $skill->category->slug,
            'title' => 'Replacement title',
            'summary' => 'Replacement summary.',
            'instructions' => 'Replacement instructions.',
        ])->assertConflict();

        $this->assertDatabaseHas('skills', ['id' => $skill->id, 'title' => $skill->title, 'is_published' => true]);
    }

    public function test_categories_and_filtered_skills_can_be_listed(): void
    {
        $category = Category::factory()->create();
        $draft = Skill::factory()->for($category)->create();
        Skill::factory()->for($category)->published()->create();
        Skill::factory()->create();

        $this->withToken(self::TOKEN)->getJson('/api/v1/skill-categories')
            ->assertOk()->assertJsonFragment(['slug' => $category->slug, 'name' => $category->name]);

        $this->getJson('/api/v1/skills?'.http_build_query(['category' => $category->slug, 'status' => 'draft', 'per_page' => 1]))
            ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.slug', $draft->slug);
    }
}
