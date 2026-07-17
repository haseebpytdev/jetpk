<?php

namespace App\Console\Commands;

use App\Support\Fixtures\JetpkHomepageAuditFixtureBuilder;
use Illuminate\Console\Command;

class JetpkLocalAuditFixtureCommand extends Command
{
    protected $signature = 'jetpk:local-audit-fixture
                            {--seed : Create jetpk profile with draft/published homepage for local audits}
                            {--profile=jetpk : Client profile slug}';

    protected $description = 'Seed local JetPK homepage fixture for content/media audit CLI gates (local/testing only)';

    public function handle(JetpkHomepageAuditFixtureBuilder $builder): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing fixture seed in production.');

            return self::FAILURE;
        }

        if (! $this->option('seed')) {
            $this->error('Specify --seed to create the local audit fixture.');

            return self::FAILURE;
        }

        if (! in_array(app()->environment(), ['local', 'testing'], true)) {
            $this->error('Refusing fixture seed outside local/testing environments.');

            return self::FAILURE;
        }

        $slug = trim((string) $this->option('profile'));
        if ($slug === '') {
            $this->error('Profile slug cannot be empty.');

            return self::FAILURE;
        }

        $result = $builder->seed($slug);

        $this->line('profile_slug='.$result['profile']->slug);
        $this->line('published_setting_id='.$result['published']->id);
        $this->line('draft_setting_id='.$result['draft']->id);
        $this->info('JetPK local audit fixture seeded. Run jetpk:homepage-content-audit and jetpk:homepage-media-audit with --profile='.$slug);

        return self::SUCCESS;
    }
}
