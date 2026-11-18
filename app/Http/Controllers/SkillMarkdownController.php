<?php

namespace App\Http\Controllers;

use App\Http\Resources\Api\Public\V1\SkillResource;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SkillMarkdownController extends Controller
{
    public function __invoke(Request $request, string $slug): Response
    {
        $skill = Skill::query()->published()->with('category')->where('slug', $slug)->firstOrFail();
        $data = (new SkillResource($skill))->resolve($request);
        $sections = ['# '.$data['title'], $data['summary']];
        $metadata = [
            'Canonical URL' => $data['url'],
            'JSON URL' => $data['json_url'],
            'Markdown URL' => $data['markdown_url'],
            'Instructions download URL' => $data['download_url'],
            'Category' => $data['category']['name'] ?? null,
            'Source URL' => $data['source_url'],
            'Source commit' => $data['source_commit'],
            'License' => $data['license'],
            'Version' => $data['version'],
            'Listing approved at' => $data['listing_approved_at'],
            'Created at' => $data['created_at'],
            'Updated at' => $data['updated_at'],
        ];
        foreach ($metadata as $label => $value) {
            if (filled($value)) {
                $sections[] = '**'.$label.':** '.$value;
            }
        }
        $sections[] = '## Catalog information';
        $sections[] = 'The following descriptions are catalog information. Listing approval does not mean the skill has been tested or is safe for every environment.';
        foreach ([
            'editor_note' => "Editor's note",
            'use_cases' => 'When to use it',
            'limitations' => 'Limitations',
            'compatibility' => 'Compatibility',
            'setup_tip' => 'Setup',
            'sample_input' => 'Sample input',
            'sample_output' => 'Sample output',
        ] as $field => $label) {
            if (blank($data[$field])) {
                continue;
            }
            $value = $data[$field];
            if (is_array($value)) {
                $value = implode("\n", array_map(
                    fn (mixed $item): string => '- '.(is_array($item) ? json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : (string) $item),
                    $value,
                ));
            }
            $sections[] = '### '.$label."\n\n".$value;
        }
        $sections[] = '## Skill instructions';
        $sections[] = $data['instructions'] ?? 'No instructions are available in this catalog record.';

        return response(implode("\n\n", $sections)."\n", 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
            'Link' => '<'.$data['url'].'>; rel="canonical", <'.$data['json_url'].'>; rel="alternate"; type="application/json"',
        ]);
    }
}
