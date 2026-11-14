<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Skill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SkillCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            'architecture-code-review' => [
                'category' => 'Software & Development',
                'skill' => [
                    'title' => 'Architecture Code Review & Security Auditor',
                    'summary' => 'A senior architecture review prompt for spotting N+1 queries, authorization flaws such as IDOR, and unnecessary abstractions before opening a pull request.',
                    'instructions' => 'You are a senior systems architect and application security auditor. Review the code for data integrity first, then performance bottlenecks and authorization flaws.

RULES:
1. Start with the findings.
2. Label each finding [CRITICAL], [MODERATE], or [IMPROVEMENT].
3. Prioritize N+1 database queries, missing transaction blocks, and insecure direct object references (IDOR).
4. Include a ready-to-use code fix for each critical finding.
5. If there are no critical risks, reply only "Appears safe for production."',
                    'editor_note' => '60% more accurate than standard prompts at catching N+1 queries, authorization flaws, and unnecessary abstractions before opening a pull request.',
                    'version' => '2.4',
                    'license' => 'MIT',
                    'setup_tip' => 'Paste the instructions into .cursor/rules/security.mdc in your project root to reuse the skill in Cursor.',
                    'sample_input' => 'public function cancelOrder(Request $request, $id) {
    $order = Order::find($id);
    $order->status = \'cancelled\';
    $order->save();
    $request->user()->increment(\'wallet_balance\', $order->total);
    return response()->json([\'ok\' => true]);
}',
                    'sample_output' => 'DB::transaction(function () use ($id, $user) {
    $order = Order::where(\'id\', $id)->where(\'user_id\', $user->id)->lockForUpdate()->firstOrFail();
    // Cancel the order and refund the balance...
});',
                    'use_cases' => ['Payment and authorization endpoints', 'High-traffic APIs where N+1 queries are a concern'],
                    'limitations' => ['The review can be too strict during rapid prototyping.', 'Split files longer than 800 lines into smaller chunks.'],
                    'compatibility' => [
                        ['model' => 'Claude 3.7 / 4 Sonnet', 'success' => 98],
                        ['model' => 'Cursor / Windsurf Agent', 'success' => 94],
                        ['model' => 'Gemini 2.5 / 3 Pro', 'success' => 91],
                        ['model' => 'GPT-4o / o3-mini', 'success' => 88],
                    ],
                ],
                'review' => [
                    'author' => 'Mert Y. — Backend Developer',
                    'model' => 'Claude 3.7 Sonnet',
                    'verdict' => 'Exceeded Expectations',
                    'body' => 'It caught a race condition we missed in the payment webhook controller of our Laravel project on the first pass.',
                ],
            ],
            'academic-source-synthesizer' => [
                'category' => 'Content & Editorial',
                'skill' => [
                    'title' => 'Academic & Editorial Source Synthesizer',
                    'summary' => 'Summarizes long reports and anchors each claim to a direct quotation from the source to reduce hallucinations.',
                    'instructions' => 'Use only the supplied source texts. Cite each main conclusion with [Source Paragraph #] and mark anything uncertain as \'Not stated in the source.\'',
                ],
                'review' => [
                    'author' => 'Selin D. — Technology Editor',
                    'model' => 'Gemini 2.5 Pro',
                    'verdict' => 'Exceeded Expectations',
                    'body' => 'It stopped inventing statistics when I turned a 45-page industry report into a newsletter.',
                ],
            ],
            'sql-data-analysis' => [
                'category' => 'Data & Research',
                'skill' => [
                    'title' => 'SQL & Data Analysis Architect',
                    'summary' => 'Turns raw table schemas into optimized PostgreSQL or MySQL queries for SaaS metrics such as churn, cohort performance, and lifetime value.',
                    'instructions' => 'Review the schema and use common table expressions (CTEs) to write a readable, index-friendly query for the requested business metric. List edge cases such as NULL values and time zone differences after the query.',
                ],
                'review' => [
                    'author' => 'Caner A. — Data Analyst',
                    'model' => 'GPT-4o',
                    'verdict' => 'Helpful',
                    'body' => 'It cut my monthly cohort analysis from 20 minutes to 2. The time zone warning saved me a lot of trouble.',
                ],
            ],
            'micro-saas-copywriter' => [
                'category' => 'Product & Design',
                'skill' => [
                    'title' => 'Micro-SaaS UX & Conversion Copywriter',
                    'summary' => 'Writes clear landing page and onboarding copy focused on a concrete user problem.',
                    'instructions' => 'Write three value proposition headlines with supporting copy. Describe what the product delivers and how much time it saves. Avoid inflated marketing terms such as \'revolutionary\' and \'flawless.\'',
                ],
                'review' => [
                    'author' => 'Zeynep T. — Indie Maker',
                    'model' => 'Claude 3.7 Sonnet',
                    'verdict' => 'Exceeded Expectations',
                    'body' => 'Our landing page conversion rate went from 2.1% to 3.8% after we changed the copy.',
                ],
            ],
        ];

        DB::transaction(function () use ($catalog): void {
            foreach ($catalog as $slug => $entry) {
                $category = Category::firstOrCreate(
                    ['slug' => Str::slug($entry['category'])],
                    ['name' => $entry['category']],
                );
                $skill = Skill::firstOrCreate(['slug' => $slug], [
                    'category_id' => $category->id,
                    'is_published' => true,
                    ...$entry['skill'],
                ]);

                if ($skill->wasRecentlyCreated) {
                    $skill->reviews()->create([...$entry['review'], 'is_approved' => true]);
                }
            }
        });
    }
}
