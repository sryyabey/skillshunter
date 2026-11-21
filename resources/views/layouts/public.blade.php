<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>@yield('title')</title>
  <link rel="icon" href="/favicon.ico" type="image/vnd.microsoft.icon" sizes="16x16 32x32 48x48">
  @stack('meta')
  <link rel="stylesheet" href="{{ asset('css/skill-comparison.css') }}">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;1,6..72,400&family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            serif: ['Newsreader', 'Georgia', 'serif'],
            sans: ['Plus Jakarta Sans', 'system-ui', 'sans-serif'],
            mono: ['JetBrains Mono', 'monospace'],
          },
          colors: {
            paper: '#FAF9F6',
            ink: '#141413',
            muted: '#5C5B57',
            borderline: '#E5E2DC',
            emeraldTrust: '#0F5C4C',
            amberNote: '#FEF7E6',
          }
        }
      }
    }
  </script>
</head>
<body class="min-h-screen flex flex-col bg-paper text-ink font-sans antialiased selection:bg-emeraldTrust selection:text-white">
  @hasSection('status-message')
    <div class="border-b border-borderline bg-[#F3F1EC] text-xs text-muted">
      <div class="max-w-6xl mx-auto px-4 sm:px-6 py-2 flex flex-wrap items-center justify-between gap-2">
        <div class="flex items-center gap-2">
          <span class="inline-block w-2 h-2 rounded-full bg-emeraldTrust"></span>
          <span>@yield('status-message')</span>
        </div>
        <div class="font-mono text-[11px] text-ink/70">@yield('status-meta')</div>
      </div>
    </div>
  @endif
  <header class="sticky top-0 z-30 bg-paper/90 backdrop-blur-md border-b border-borderline">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 min-h-16 py-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
      <a href="{{ route('welcome') }}" class="flex shrink-0 items-center gap-2.5">
        <img src="{{ asset('images/logo.webp') }}" alt="" width="32" height="32" class="size-8 shrink-0 rounded-lg">
        <span class="font-serif text-xl font-semibold tracking-tight text-ink">skillshunter<span class="text-emeraldTrust">.net</span></span>
      </a>

      <nav aria-label="Primary" class="hidden xl:flex items-center gap-5 text-sm font-medium text-muted">
        @yield('navigation')
      </nav>

      <div class="flex empty:hidden basis-full flex-wrap items-center gap-2 sm:basis-auto">
        @yield('header-actions')
      </div>
    </div>
  </header>
  <main id="main-content" class="flex-1">
    @yield('content')
  </main>

  @include('layouts.partials.footer')

  @include('catalog.compare-tray')
  <script type="module" src="{{ asset('js/skill-comparison.js') }}"></script>
  @stack('scripts')
</body>
</html>
