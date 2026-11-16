<?php

namespace Tests\Feature;

use App\Filament\Manage\Resources\Reviews\Pages\ManageReviews;
use App\Filament\Manage\Resources\Skills\Pages\ManageSkills;
use App\Models\Review;
use App\Models\Skill;
use App\Models\SkillSuggestion;
use App\Models\User;
use Database\Seeders\SkillCatalogSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SkillCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_seeds_repeatedly_and_lists_published_skills_by_category_and_query(): void
    {
        $this->seed(SkillCatalogSeeder::class);
        $this->seed(SkillCatalogSeeder::class);
        $this->assertDatabaseCount('skills', 4);
        $this->assertDatabaseCount('reviews', 4);

        $this->get(route('welcome', ['category' => 'data-research', 'q' => 'SQL']))
            ->assertOk()->assertSee('1 SKILL FOUND')->assertSee('SQL & Data Analysis Architect');

        $draft = Skill::factory()->create();
        $this->get(route('detail', $draft->slug))->assertNotFound();
        $this->get(route('detail', 'architecture-code-review'))
            ->assertOk()->assertDontSee('Input & Output Examples', false)->assertDontSee('Last tested:')->assertSee('System Instructions');
    }

    public function test_home_status_reflects_authenticated_worker_activity_and_expires(): void
    {
        $finderToken = str_repeat('f', 40);
        $controllerToken = str_repeat('q', 40);
        config(['skill-integration.token' => $finderToken, 'skill-integration.quality_token' => $controllerToken]);
        $finder = '/api/v1/worker-status/finder';
        $controller = '/api/v1/worker-status/controller';
        $run = ['run_id' => (string) Str::uuid(), 'active' => true];

        $this->get('/')->assertOk()->assertSee('Browse original skill instructions');
        $this->postJson($finder, $run)->assertUnauthorized();
        $this->withToken($finderToken)->postJson($controller, $run)->assertUnauthorized();
        $this->postJson($finder, $run)->assertNoContent();
        $this->get('/')->assertSee('Discovering and cataloging skills');

        $this->withToken($controllerToken)->postJson($controller, $run)->assertNoContent();
        $this->get('/')->assertSee('Checking a skill’s instructions and examples', false);
        $this->postJson($controller, ['run_id' => (string) Str::uuid(), 'active' => false])->assertNoContent();
        $this->get('/')->assertSee('Checking a skill’s instructions and examples', false);
        $this->postJson($controller, ['active' => false] + $run)->assertNoContent();
        $this->get('/')->assertSee('Discovering and cataloging skills');

        $skill = Skill::factory()->create(['title' => 'Private draft title']);
        $skill->forceFill(['ai_reviewed_at' => now()])->save();
        $this->travel(91)->seconds();
        $this->get('/')->assertSee('Latest source review completed')->assertDontSee('Private draft title');
        $this->travelBack();
    }

    public function test_catalog_search_ranks_relevant_titles_and_matches_keywords_across_fields(): void
    {
        $strong = Skill::factory()->published()->create([
            'title' => 'Excel Automation Toolkit', 'summary' => 'Process files in batches.',
        ]);
        $keyword = Skill::factory()->published()->create([
            'title' => 'Workbook Workflow', 'summary' => 'Validate formulas.', 'seo_keywords' => ['spreadsheet automation'],
        ]);
        Skill::factory()->published()->create(['title' => 'Excel Templates', 'summary' => 'Download a sample workbook.']);
        Skill::factory()->create(['title' => 'Excel Automation Draft']);

        $this->get(route('welcome', ['q' => 'excel automation']))->assertOk()
            ->assertViewHas('skills', fn ($skills): bool => $skills->pluck('id')->all() === [$strong->id, $keyword->id]);

        $this->get(route('welcome', ['q' => 'excel için otomasyon', 'category' => $keyword->category->slug]))->assertOk()
            ->assertViewHas('skills', fn ($skills): bool => $skills->pluck('id')->all() === [$keyword->id]);

        $this->get(route('welcome', ['q' => '%__']))->assertOk()
            ->assertViewHas('skills', fn ($skills): bool => $skills->total() === 0);
    }

    public function test_empty_search_suggests_published_partial_matches_in_the_active_category(): void
    {
        $best = Skill::factory()->published()->create([
            'title' => 'Workbook Workflow', 'seo_keywords' => ['spreadsheet automation'],
        ]);
        $weaker = Skill::factory()->published()->for($best->category)->create(['title' => 'Excel Templates']);
        Skill::factory()->for($best->category)->create(['title' => 'Excel Automation Dashboard']);
        Skill::factory()->published()->create(['title' => 'Excel Automation Dashboard']);

        $query = 'excel automation dashboard';
        $category = $best->category->slug;
        $this->get(route('welcome', ['q' => $query, 'category' => $category]))
            ->assertOk()
            ->assertSee('0 SKILLS FOUND')
            ->assertSee('Search all categories')
            ->assertSee(route('welcome', ['q' => $query]))
            ->assertSee(route('welcome', ['category' => $category]))
            ->assertSeeInOrder(['Related skills', $best->title, $weaker->title])
            ->assertViewHas('relatedSkills', fn ($skills): bool => $skills->pluck('slug')->all() === [$best->slug, $weaker->slug]);
    }

    public function test_catalog_only_shows_suggested_searches_with_published_results(): void
    {
        Skill::factory()->published()->create(['title' => 'Architecture Code Review']);
        Skill::factory()->create(['title' => 'Presentation Builder']);

        $this->get(route('welcome'))
            ->assertOk()
            ->assertSee('id="catalog-search" action="'.route('welcome').'"', false)
            ->assertSee('Review code')
            ->assertSee(route('welcome', ['q' => 'code review']))
            ->assertDontSee('Create a presentation');
    }

    public function test_search_without_partial_matches_displays_catalog_skills_under_the_empty_message(): void
    {
        $published = Skill::factory()->published()->create(['title' => 'Workbook Workflow']);
        $draft = Skill::factory()->create(['title' => 'Private Skill']);

        $this->get(route('welcome', ['q' => 'unmatchablequery', '_catalog_results' => 1]))
            ->assertOk()
            ->assertSee('0 SKILLS FOUND')
            ->assertSeeInOrder(['No skills found.', 'Try a shorter search or a different term.', 'Explore the catalog', $published->title])
            ->assertSee(route('detail', $published->slug))
            ->assertDontSee($draft->title);
    }

    public function test_catalog_search_can_return_only_the_results_with_clean_pagination_links(): void
    {
        Skill::factory()->published()->count(13)->sequence(
            fn ($sequence): array => ['title' => 'Spreadsheet Template '.$sequence->index],
        )->create();

        $this->get(route('welcome', ['q' => 'spreadsheet', '_catalog_results' => 1]))
            ->assertOk()
            ->assertViewIs('catalog.results')
            ->assertSee('13 SKILLS FOUND')
            ->assertSee(route('welcome', ['q' => 'spreadsheet', 'page' => 2]))
            ->assertDontSee('_catalog_results')
            ->assertDontSee('#skill-directory')
            ->assertDontSee('Suggest a Skill');
    }

    public function test_detail_displays_source_backed_input_and_renders_long_markdown_safely(): void
    {
        $source = "---\nname: demo\nquickPrompts: Plan a short presentation\n---\n\n"
            ."# Workflow\n\n| Stage | Result |\n| --- | --- |\n| Plan | Outline |\n\n"
            ."<script>alert(1)</script>\n\n![Example](https://example.com/tracking.gif)\n\n"
            ."[Unsafe](javascript:alert%281%29)\n\n".str_repeat("Documented workflow.\n", 150);
        $skill = Skill::factory()->published()->create([
            'source_url' => 'https://github.com/example/demo/blob/main/SKILL.md',
            'source_markdown' => $source,
            'sample_input' => 'Plan a short presentation',
            'sample_output' => 'Invented example.',
        ]);

        $this->get(route('detail', $skill->slug))->assertOk()
            ->assertSeeInOrder(['Try this prompt', 'Plan a short presentation', 'View original Markdown'])
            ->assertSee('<h3>Workflow</h3>', false)->assertSee('<table>', false)
            ->assertDontSee('Example output from the source')->assertDontSee('Invented example.')
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('src="https://example.com/tracking.gif"', false)
            ->assertDontSee('href="javascript:', false);

        $response = $this->get(route('skills.download', $skill->slug))->assertOk();
        $this->assertSame($source, $response->streamedContent());
    }

    public function test_source_import_replaces_public_generated_instructions_and_downloads_exact_markdown(): void
    {
        $source = 'https://github.com/example/project/blob/main/skills/demo/SKILL.md';
        $raw = 'https://raw.githubusercontent.com/example/project/main/skills/demo/SKILL.md';
        $markdown = "---\nname: demo\n---\n\n<example>\n";
        $skill = Skill::factory()->published()->create([
            'source_url' => $source,
            'instructions' => 'Generated trigger text',
            'setup_tip' => 'Run invented.py',
            'sample_output' => 'Generated example',
            'limitations' => ['Generated limitation'],
        ]);
        $this->get(route('detail', $skill->slug))->assertOk()
            ->assertSee($source)->assertDontSee('Generated trigger text')->assertDontSee('Copy Instructions');
        $this->get(route('skills.download', $skill->slug))->assertNotFound();

        Http::preventStrayRequests();
        Http::fake([$raw => Http::response($markdown)]);
        $this->artisan('skills:refresh-sources', ['slug' => $skill->slug])->assertSuccessful();

        $this->get(route('detail', $skill->slug))->assertOk()
            ->assertSee('<example>')->assertDontSee('<example>', false)->assertSee('Copy Instructions')
            ->assertDontSee('invented.py')->assertDontSee('Generated example')->assertDontSee('Generated limitation');
        $response = $this->get(route('skills.download', $skill->slug))->assertOk()->assertDownload('SKILL.md');
        $this->assertSame($markdown, $response->streamedContent());
    }

    public function test_browse_prioritizes_distinct_repositories_and_hides_empty_review_feed(): void
    {
        $other = Skill::factory()->published()->create(['source_url' => 'https://github.com/example/other/blob/main/SKILL.md']);
        $older = Skill::factory()->published()->create(['source_url' => 'https://github.com/example/same/blob/main/old/SKILL.md']);
        $newer = Skill::factory()->published()->create(['source_url' => 'https://github.com/example/same/blob/main/new/SKILL.md']);

        $this->get(route('welcome'))->assertOk()
            ->assertSeeInOrder([$newer->title, $other->title, $older->title])
            ->assertSee('<meta name="description"', false)
            ->assertDontSee('Latest Field Reports')->assertDontSee('0 reviews');
    }

    public function test_review_stays_hidden_until_approved(): void
    {
        $skill = Skill::factory()->published()->create();
        $review = Review::factory()->for($skill)->make();

        $this->post(route('reviews.store'), [...$review->only(['author', 'model', 'verdict', 'body']), 'skill' => $skill->slug, 'is_approved' => true])
            ->assertRedirect(route('detail', $skill->slug).'#reviews');

        $stored = $skill->reviews()->sole();
        $this->assertFalse($stored->is_approved);
        $this->get(route('detail', $skill->slug))->assertOk()->assertDontSee($review->body);

        $stored->update(['is_approved' => true]);
        $this->get(route('detail', $skill->slug))->assertOk()->assertSee($review->body);
        $this->get(route('welcome'))->assertOk()->assertSee($review->body);
    }

    public function test_reviews_validate_published_skill_and_verdict(): void
    {
        $skill = Skill::factory()->create();
        $review = Review::factory()->for($skill)->make(['verdict' => 'Invalid']);

        $this->post(route('reviews.store'), [...$review->only(['author', 'model', 'verdict', 'body']), 'skill' => $skill->slug])
            ->assertSessionHasErrors(['skill', 'verdict']);

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_guest_can_suggest_a_skill(): void
    {
        $suggestion = SkillSuggestion::factory()->make();

        $this->post(route('suggestions.store'), $suggestion->toArray())->assertRedirect(route('welcome'));
        $this->assertDatabaseHas('skill_suggestions', ['title' => $suggestion->title]);
    }

    public function test_vote_is_counted_once_per_session_and_pending_reviews_cannot_be_voted_on(): void
    {
        $skill = Skill::factory()->published()->create();
        $approved = Review::factory()->for($skill)->approved()->create();
        $pending = Review::factory()->for($skill)->create();

        foreach (range(1, 2) as $attempt) {
            $this->post(route('skills.vote', $skill->slug))->assertRedirect();
            $this->post(route('reviews.vote', $approved->id))->assertRedirect();
        }

        $this->post(route('reviews.vote', $pending->id))->assertNotFound();
        $this->assertDatabaseCount('skill_votes', 1);
        $this->assertDatabaseCount('review_votes', 1);
    }

    public function test_visitors_can_rate_a_skill_and_update_their_own_rating(): void
    {
        $skill = Skill::factory()->published()->create();
        $otherSkill = Skill::factory()->published()->create();
        $detail = route('detail', $skill->slug);
        $rating = route('skills.rating.store', $skill->slug);

        $this->get($detail)->assertOk()->assertSee('No ratings yet');
        $this->post($rating, ['rating' => 2])->assertRedirect($detail.'#skill-rating');
        $this->post($rating, ['rating' => 5])->assertRedirect($detail.'#skill-rating');
        $this->assertDatabaseCount('skill_ratings', 1);
        $this->get($detail)->assertOk()
            ->assertSee('Your rating was saved.')
            ->assertSee('5.0 / 5')
            ->assertViewHas('visitorRating', 5);

        $this->withSession(['_token' => str_repeat('b', 40)])
            ->post($rating, ['rating' => 2])->assertRedirect($detail.'#skill-rating');
        $this->get($detail)->assertOk()->assertSee('3.5 / 5')->assertSee('2 ratings')
            ->assertViewHas('visitorRating', 2);
        $this->get(route('detail', $otherSkill->slug))->assertOk()->assertSee('No ratings yet')
            ->assertViewHas('visitorRating', null);
    }

    public function test_skill_ratings_require_a_published_skill_and_an_integer_from_one_to_five(): void
    {
        $skill = Skill::factory()->published()->create();
        $draft = Skill::factory()->create();

        foreach ([0, 6, 2.5, [3], null] as $invalidRating) {
            $this->from(route('detail', $skill->slug))
                ->post(route('skills.rating.store', $skill->slug), ['rating' => $invalidRating])
                ->assertRedirect(route('detail', $skill->slug))
                ->assertSessionHasErrors('rating');
        }

        $this->post(route('skills.rating.store', $draft->slug), ['rating' => 3])->assertNotFound();
        $this->assertDatabaseCount('skill_ratings', 0);
    }

    public function test_filament_catalog_requires_panel_role_and_resource_permission(): void
    {
        $path = route('filament.manage.resources.skills.index');
        $this->get($path)->assertRedirect(route('filament.manage.auth.login'));

        $user = User::factory()->create();
        $this->actingAs($user)->get($path)->assertForbidden();

        $user->assignRole(Role::create(['name' => 'panel_user']));
        $user->givePermissionTo(Permission::create(['name' => 'ViewAny:Skill']));
        $this->actingAs($user)->get($path)->assertOk();

        Skill::factory()->published()->create();
        $this->get(route('filament.manage.pages.dashboard'))
            ->assertOk()
            ->assertSee('SkillsHunter')
            ->assertSee('Published skills')
            ->assertDontSee('Pending reviews');

        $user->givePermissionTo(Permission::create(['name' => 'ViewAny:Review']));
        $review = Review::factory()->create();
        $published = Review::factory()->approved()->create();

        $this->get(route('filament.manage.pages.dashboard'))
            ->assertOk()->assertSee('Pending reviews');

        $this->get(route('filament.manage.resources.reviews.index', ['filters' => ['is_approved' => ['value' => '0']]]))
            ->assertOk()->assertSee($review->author)->assertDontSee($published->author);

        Livewire::test(ManageReviews::class)->assertTableActionHidden('publish', $review);

        $user->givePermissionTo(Permission::create(['name' => 'Update:Review']));
        Livewire::test(ManageReviews::class)
            ->assertTableActionVisible('publish', $review)
            ->callTableAction('publish', $review);

        $this->assertTrue($review->refresh()->is_approved);
    }

    public function test_editor_can_bulk_update_publication_and_verification_independently(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole(Role::create(['name' => 'panel_user']));
        foreach (['ViewAny:Skill', 'Update:Skill'] as $name) {
            $editor->givePermissionTo(Permission::create(['name' => $name]));
        }
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        $skills = Skill::factory()->count(2)->create();
        $other = Skill::factory()->create();

        Livewire::test(ManageSkills::class)
            ->callTableBulkAction('setPublicationStatus', $skills, ['is_published' => 'published']);
        Livewire::test(ManageSkills::class)
            ->callTableBulkAction('setVerificationDate', $skills, ['verified_at' => '2026-11-14']);
        Livewire::test(ManageSkills::class)
            ->callTableBulkAction('setPublicationStatus', [$skills[0]], ['is_published' => 'draft']);

        $this->assertFalse($skills[0]->refresh()->is_published);
        $this->assertTrue($skills[1]->refresh()->is_published);
        foreach ($skills as $skill) {
            $this->assertSame('2026-11-14', $skill->verified_at->toDateString());
        }
        $this->assertFalse($other->refresh()->is_published);
        $this->assertNull($other->verified_at);
    }

    public function test_bulk_publish_keeps_eligible_drafts_published_when_other_drafts_fail_checks(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole(Role::create(['name' => 'panel_user']));
        foreach (['ViewAny:Skill', 'Update:Skill'] as $name) {
            $editor->givePermissionTo(Permission::create(['name' => $name]));
        }
        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('manage'));

        $commit = str_repeat('a', 40);
        $source = [
            'source_commit' => $commit,
            'source_url' => "https://github.com/example/repo/blob/{$commit}/SKILL.md",
            'source_markdown' => "# Example\nActual instructions.",
        ];
        $first = Skill::factory()->create($source + ['verified_at' => today()]);
        $placeholder = Skill::factory()->create(['source_markdown' => '# Insert instructions below'] + $source + ['verified_at' => today()]);
        $unapproved = Skill::factory()->create($source);
        $last = Skill::factory()->create($source + ['verified_at' => today()]);

        Livewire::test(ManageSkills::class)
            ->callTableBulkAction('setPublicationStatus', [$first, $placeholder, $unapproved, $last], ['is_published' => 'published'])
            ->assertNotified('2 updated, 2 skipped');

        $this->assertTrue($first->refresh()->is_published);
        $this->assertTrue($last->refresh()->is_published);
        $this->assertFalse($placeholder->refresh()->is_published);
        $this->assertFalse($unapproved->refresh()->is_published);
    }

    public function test_editor_can_approve_and_publish_a_pinned_draft_with_an_existing_approval_date(): void
    {
        $editor = User::factory()->create();
        $editor->assignRole(Role::create(['name' => 'panel_user']));
        foreach (['ViewAny:Skill', 'Update:Skill'] as $name) {
            $editor->givePermissionTo(Permission::create(['name' => $name]));
        }
        $commit = str_repeat('a', 40);
        $skill = Skill::factory()->create([
            'source_commit' => $commit,
            'source_url' => "https://github.com/example/repo/blob/{$commit}/SKILL.md",
            'source_markdown' => "# Example\nActual instructions.",
            'verified_at' => today(),
        ]);
        $this->assertNull($skill->approved_by);

        $this->actingAs($editor);
        Filament::setCurrentPanel(Filament::getPanel('manage'));
        Livewire::test(ManageSkills::class)
            ->callTableAction('approveAndPublish', $skill)
            ->assertNotified('Skill published');

        $this->assertTrue($skill->refresh()->is_published);
        $this->assertSame($editor->id, $skill->approved_by);
        $this->get(route('detail', $skill->slug))->assertOk();
    }

    public function test_sitemap_lists_the_canonical_home_and_only_published_skills(): void
    {
        config(['app.url' => 'https://skillshunter.net']);
        $published = Skill::factory()->published()->create(['slug' => 'public-skill']);
        Skill::factory()->create(['slug' => 'draft-skill']);

        $response = $this->get('/sitemap.xml')->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $xml = simplexml_load_string($response->getContent());

        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->getNamespaces()['']);
        $this->assertCount(2, $xml->url);
        $this->assertSame('https://skillshunter.net/', (string) $xml->url[0]->loc);
        $this->assertSame('https://skillshunter.net/skills/public-skill', (string) $xml->url[1]->loc);
        $this->assertSame($published->updated_at->toAtomString(), (string) $xml->url[1]->lastmod);
    }
}
