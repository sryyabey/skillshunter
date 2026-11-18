<?php

namespace Tests\Feature;

use App\Http\Resources\Api\Public\V1\SkillResource;
use App\Models\Skill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PublicSkillResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_data_excludes_internal_attributes_and_loaded_relations(): void
    {
        $skill = Skill::factory()->published()->create([
            'source_url' => 'https://example.com/SKILL.md',
            'source_markdown' => 'Original source instructions.',
            'instructions' => 'Internal alternative instructions.',
        ]);
        $skill->forceFill([
            'approved_by' => 123,
            'ai_review_details' => ['private' => 'Internal review'],
            'ai_review_lease_hash' => 'secret-lease',
            'future_private_field' => 'must never be exposed',
        ]);
        $skill->setRelation('reviews', collect([['author' => 'Private person']]));

        $data = (new SkillResource($skill))->resolve();

        $this->assertSame('Original source instructions.', $data['instructions']);
        $this->assertSame($skill->category->slug, $data['category']['slug']);
        $this->assertSame(route('detail', $skill->slug), $data['url']);
        foreach (['id', 'category_id', 'approved_by', 'ai_review_details', 'ai_review_lease_hash', 'future_private_field', 'reviews', 'source_markdown'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
    }

    public function test_unpublished_skills_cannot_be_serialized_for_public_access(): void
    {
        $skill = Skill::factory()->create();

        try {
            (new SkillResource($skill))->resolve();
            $this->fail('An unpublished skill was exposed.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_missing_fields_are_not_invented_and_legacy_instructions_are_supported(): void
    {
        $skill = Skill::factory()->published()->create(['instructions' => 'Legacy instructions.']);
        $data = (new SkillResource($skill))->resolve();

        $this->assertSame('Legacy instructions.', $data['instructions']);
        foreach (['use_cases', 'limitations', 'compatibility', 'seo_keywords'] as $field) {
            $this->assertSame([], $data[$field]);
        }
        foreach (['source_url', 'source_commit', 'version', 'listing_approved_at', 'sample_input', 'sample_output'] as $field) {
            $this->assertNull($data[$field]);
        }
    }
}
