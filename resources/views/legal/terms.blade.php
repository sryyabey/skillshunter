@extends('layouts.public')

@section('title', 'Terms of Use — SkillsHunter')

@section('content')
  <article class="max-w-3xl mx-auto px-4 sm:px-6 py-12 space-y-6 text-muted leading-relaxed">
    <h1 class="font-serif text-4xl text-ink">Terms of Use</h1>

    <section class="space-y-2">
      <h2 class="font-serif text-2xl text-ink">Catalog entries and licenses</h2>
      <p>SkillsHunter lists AI skills from third-party sources. The original source and its license govern your use, modification, and redistribution of a skill. Check the original source for current instructions and license terms before using or sharing it.</p>
      <p>SkillsHunter is an informational directory of third-party AI skills. A listing or catalog approval does not guarantee that a skill is safe, accurate, or compatible with your environment. You are responsible for reviewing a skill before use and deciding what permissions and access to grant it. Nothing in these terms limits rights or responsibilities that cannot be limited under applicable law.</p>
    </section>

    <section class="space-y-2">
      <h2 class="font-serif text-2xl text-ink">Instructions and examples</h2>
      <p>Catalog descriptions and examples may be drafted with AI assistance. Listing approval covers inclusion in the catalog. Review any instructions, permissions, and dependencies before running a skill, and check its output before relying on it.</p>
    </section>

    <section class="space-y-2">
      <h2 class="font-serif text-2xl text-ink">Reviews and suggestions</h2>
      <p>Share content you have permission to submit. Keep reviews relevant to your own experience, and leave out credentials, confidential information, and other people’s personal details. We may decline or remove spam, abusive content, or submissions unrelated to the skill.</p>
      <p>When you submit a review, you allow SkillsHunter to display it with the name or role you provide if the review is approved. See our <a class="text-emeraldTrust underline" href="{{ route('privacy') }}">Privacy Notice</a> for how we handle submissions.</p>
    </section>
  </article>
@endsection
