<?php

namespace App\Http\Controllers;

use App\Models\Skill;
use Illuminate\Http\Response;
use XMLWriter;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $origin = rtrim(config('app.url'), '/');
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElementNS(null, 'urlset', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $xml->startElement('url');
        $xml->writeElement('loc', $origin.route('welcome', absolute: false));
        $xml->endElement();

        foreach (Skill::published()->select(['id', 'slug', 'updated_at'])->lazyById() as $skill) {
            $xml->startElement('url');
            $xml->writeElement('loc', $origin.route('detail', $skill->slug, absolute: false));
            if ($skill->updated_at) {
                $xml->writeElement('lastmod', $skill->updated_at->toAtomString());
            }
            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return response($xml->outputMemory(), headers: ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
