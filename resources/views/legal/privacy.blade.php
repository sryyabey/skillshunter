@extends('layouts.public')

@section('title', 'Privacy Notice — SkillsHunter')

@section('content')
  <article class="max-w-3xl mx-auto px-4 sm:px-6 py-12 space-y-6 text-muted leading-relaxed">
    <h1 class="font-serif text-4xl text-ink">Privacy Notice</h1>

    @if ($operator = config('legal.operator'))
      <p>{{ $operator }} operates SkillsHunter and is responsible for the personal data described here.</p>
    @endif

    <section class="space-y-2">
      <h2 class="font-serif text-2xl text-ink">Reviews and suggestions</h2>
      <p>When you submit a review, we collect your display name or role, the skill and model you used, your verdict, and your comments. We use these details to moderate reviews. If approved, your display name or role and the review details appear publicly.</p>
      <p>When you suggest a skill, we collect the display name, title, and description you enter. We use suggestions to evaluate potential catalog entries. Suggestions are visible to the people who manage the catalog.</p>
    </section>

    <section class="space-y-2">
      <h2 class="font-serif text-2xl text-ink">Sessions and votes</h2>
      <p>We use session cookies to keep forms working and protect submissions. The site may record your IP address and browser information with your session. We use a value derived from your session to limit repeat votes and technical request information to limit abuse.</p>
    </section>

    <section class="space-y-2">
      <h2 class="font-serif text-2xl text-ink">External services</h2>
      <p>The site loads fonts from Google Fonts and styles from the Tailwind CSS CDN. Your browser connects to these services and sends technical information such as its IP address. Original skill links lead to external sites, which have their own privacy practices.</p>
    </section>

    <section class="space-y-2">
      <h2 class="font-serif text-2xl text-ink">Your information and requests</h2>
      <p>You can request access to, correction of, or deletion of your personal data where applicable. Include a link to the relevant review or enough detail to identify your submission.</p>
      @if ($email = config('legal.privacy_email'))
        <p>Send privacy requests to <a class="text-emeraldTrust underline" href="mailto:{{ $email }}">{{ $email }}</a>.</p>
      @endif
    </section>
  </article>
@endsection
