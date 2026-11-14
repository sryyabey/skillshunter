<?php

namespace App\Console\Commands;

use App\Http\Controllers\SitemapController;
use Illuminate\Console\Command;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Write the public sitemap XML to standard output';

    public function handle(SitemapController $sitemap): int
    {
        $this->output->write($sitemap()->getContent(), false, OutputInterface::OUTPUT_RAW);

        return self::SUCCESS;
    }
}
