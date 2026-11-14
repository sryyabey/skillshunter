<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Coming Soon | SkillsHunter</title>
    <style>
        :root {
            color-scheme: light;
            --paper: #faf9f6;
            --ink: #141413;
            --muted: #5c5b57;
            --accent: #0f5c4c;
            --border: #e5e2dc;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            display: grid;
            min-block-size: 100vh;
            min-block-size: 100svh;
            margin: 0;
            padding: clamp(1rem, 5vw, 4rem);
            place-items: center;
            background: var(--paper);
            color: var(--ink);
            font: 1rem/1.6 system-ui, sans-serif;
        }

        main {
            inline-size: min(100%, 44rem);
            padding: clamp(1.75rem, 7vw, 4.5rem);
            border: 1px solid var(--border);
            border-block-start: 4px solid var(--accent);
            border-radius: 1.5rem;
            background: #fff;
            box-shadow: 0 1rem 3rem rgb(20 20 19 / 6%);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin: 0;
            font: 600 1.25rem/1 Georgia, serif;
        }

        .logo {
            inline-size: 3.5rem;
            block-size: auto;
            border-radius: .85rem;
        }

        h1 {
            margin: clamp(2rem, 6vw, 3.5rem) 0 1rem;
            font: 500 clamp(2.75rem, 9vw, 5.5rem)/1 Georgia, serif;
            letter-spacing: -.05em;
        }

        .description {
            max-inline-size: 38ch;
            margin: 0;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <main aria-labelledby="page-title">
        <p class="brand"><img class="logo" src="/images/logo.webp" width="56" height="56" alt=""> SkillsHunter</p>
        <h1 id="page-title">Coming soon.</h1>
        <p class="description">We’re getting the SkillsHunter directory ready. Check back for curated AI skills, practical examples, and community reviews.</p>
    </main>
</body>
</html>
