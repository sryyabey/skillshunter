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
            ->assertOk()->assertSee('1 SKILLS FOUND')->assertSee('SQL & Data Analysis Architect');

        $draft = Skill::factory()->create();
        $this->get(route('detail', $draft->slug))->assertNotFound();
        $this->get(route('detail', 'architecture-code-review'))
            ->assertOk()->assertSee('Test Evidence')->assertSee('System Instructions');
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
}
