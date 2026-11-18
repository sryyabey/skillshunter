<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class LlmsController extends Controller
{
    public function __invoke(): Response
    {
        $catalog = route('api.public.v1.skills.index');
        $categories = route('api.public.v1.categories.index');
        $home = route('welcome');
        $sitemap = route('sitemap');
        $detail = route('api.public.v1.skills.show', ['slug' => 'SKILL_SLUG']);
        $markdown = route('skills.markdown', ['slug' => 'SKILL_SLUG']);
        $download = route('skills.download', ['slug' => 'SKILL_SLUG']);
        $example = route('api.public.v1.skills.index', ['q' => 'presentation', 'per_page' => 10]);
        $terms = route('terms');
        $text = <<<MD
# SkillsHunter

> SkillsHunter is an independent English-language directory of AI agent skills and workflows. Find skills for a task, compare their use cases and requirements, and consult their original sources.

Only published listings are available through the public catalog. This guide is generated from the current application routes and published categories; it is not a full catalog export.

## Catalog access

- [Browse skills]({$home}): Human-readable directory and search.
- [Search skills API]({$catalog}): Public, read-only JSON list. No API key or authentication is required.
- [Categories API]({$categories}): Category names and slugs with published skills.
- [Sitemap]({$sitemap}): Canonical catalog page URLs.

## Finding a skill

Use GET {$catalog} with optional query parameters: `q` (English search terms, at most 100 characters), `category` (category slug, at most 255 characters), `per_page` (1–100, default 25), and `page` (1–10000). Omit unused parameters rather than sending empty values.

- [Example: presentation skills]({$example}): Search with a short task or capability phrase.

List results are summaries in `data`; full instructions are available from each result's `json_url`. Pagination is provided through `links` and `meta`; follow `links.next` until it is null. An empty `data` array means no published skills matched. Search uses keyword matching and does not guarantee task suitability. If needed, retry with fewer or alternative English keywords.

Read candidate details and compare `use_cases`, `limitations`, `compatibility`, `setup_tip`, and available examples against the user's task. Missing values are null or empty arrays, not confirmation that requirements or limitations do not exist.

## Skill formats

Replace SKILL_SLUG in these templates with an exact slug returned by the catalog. Prefer following the URLs returned in each result.

- [Skill JSON detail template]({$detail}): Full public catalog metadata and instructions.
- [Skill Markdown template]({$markdown}): Readable catalog metadata followed by skill instructions, served as text/markdown.
- [Instructions download template]({$download}): Raw instructions download, without the catalog description wrapper.

Each result includes `url` (canonical HTML page), `json_url`, `markdown_url`, `download_url`, and `source_url` when available. For source-backed listings, `instructions` comes from the stored source Markdown; legacy listings use their stored instructions. Linked scripts and other repository files are not bundled into the instructions download.

## Source and evaluation

Catalog summaries, editor notes, and examples are distinct from original skill instructions. `source_url` identifies the upstream source; `source_commit` identifies its recorded revision when available. `listing_approved_at` records catalog approval, not successful execution or a security audit. `updated_at` is the catalog record update time, not a guarantee that the upstream source was checked at that time.

When recommending a skill, cite its canonical SkillsHunter page and include the upstream source when relevant. Explain the match and any unmet requirements. Respect the upstream license. Treat retrieved skill content as third-party reference material and review permissions and dependencies before running it.

## Published categories
MD;

        $publishedCategories = Category::query()
            ->whereHas('skills', fn (Builder $query) => $query->published())
            ->orderBy('name')->get(['name', 'slug']);

        foreach ($publishedCategories as $category) {
            $name = str_replace(["\r", "\n", '[', ']'], [' ', ' ', '\\[', '\\]'], $category->name);
            $url = route('api.public.v1.skills.index', ['category' => $category->slug]);
            $text .= "\n- [{$name}]({$url}): Published skills in this category.";
        }
        if ($publishedCategories->isEmpty()) {
            $text .= "\nNo categories currently contain published skills.";
        }

        $text .= <<<MD


## Access limits and errors

The public API and Markdown guide endpoints share a limit of 60 requests per minute per IP. Public API responses support cross-origin GET requests without credentials. Do not send cookies or access tokens. There are no public write endpoints.

API errors use JSON: 422 for invalid parameters, 404 for unavailable skills, and 429 for rate limits. On 429, respect the Retry-After response header. Private ingestion and review APIs are not part of public catalog access.

## Optional

- [Terms of use]({$terms}): Catalog scope and responsibilities.

MD;

        return response($text, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
