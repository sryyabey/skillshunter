<?php

namespace App\Support;

class SkillSourceReference
{
    public static function parse(string $url, string $commit): ?array
    {
        if (! preg_match('/\A[0-9a-f]{40}\z/', $commit)) {
            return null;
        }
        foreach ([
            'github' => '~\Ahttps://github\.com/([\w.-]+/[\w.-]+)/blob/([0-9a-f]{40})/([^?#]+)\z~',
            'gitlab' => '~\Ahttps://gitlab\.com/([\w./-]+)/-/blob/([0-9a-f]{40})/([^?#]+)\z~',
            'bitbucket' => '~\Ahttps://bitbucket\.org/([\w.-]+/[\w.-]+)/src/([0-9a-f]{40})/([^?#]+)\z~',
        ] as $platform => $pattern) {
            if (! preg_match($pattern, $url, $matches) || $matches[2] !== $commit) {
                continue;
            }
            $path = rawurldecode($matches[3]);
            if (array_intersect(explode('/', $matches[1].'/'.$path), ['', '.', '..'])
                || str_contains($path, '\\') || preg_match('/[\x00-\x1f]/', $path)
                || strcasecmp(basename($path), 'SKILL.md') !== 0) {
                return null;
            }

            return ['platform' => $platform, 'repository' => $matches[1], 'path' => $path];
        }

        return null;
    }

    public static function identity(array $reference): string
    {
        return hash('sha256', $reference['platform'].'/'.strtolower($reference['repository']).'/'.$reference['path']);
    }

    public static function fingerprint(string $source): string
    {
        return hash('sha256', trim(str_replace(["\r\n", "\r"], "\n", $source)));
    }
}
