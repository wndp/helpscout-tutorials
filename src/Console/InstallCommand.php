<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'tutorials:install
        {--force : Overwrite existing files when publishing}';

    protected $description = 'Publish config, slash-command templates, and tweak .env / .gitignore for HelpScout tutorials.';

    public function handle(): int
    {
        $this->publishConfig();
        $this->publishClaudeCommands();
        $this->ensureEnvLine();
        $this->ensureGitignoreLine();

        $this->info('helpscout-tutorials install complete.');
        $this->line(' - Set HELPSCOUT_DOCS_API_KEY in your .env if you have not already.');
        $this->line(' - Reload Claude Code (or run /help) for the slash commands to appear.');

        return self::SUCCESS;
    }

    private function publishConfig(): void
    {
        $args = [
            '--tag' => 'helpscout-tutorials-config',
        ];

        if ($this->option('force')) {
            $args['--force'] = true;
        }

        $this->call('vendor:publish', $args);
    }

    private function publishClaudeCommands(): void
    {
        File::ensureDirectoryExists(base_path('.claude/commands'));

        $args = [
            '--tag' => 'helpscout-tutorials-claude-commands',
        ];

        if ($this->option('force')) {
            $args['--force'] = true;
        }

        $this->call('vendor:publish', $args);
    }

    private function ensureEnvLine(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            $this->warn('.env not found at '.$envPath.' — skipping env-line check.');

            return;
        }

        $contents = File::get($envPath);

        if (str_contains($contents, 'HELPSCOUT_DOCS_API_KEY')) {
            return;
        }

        $append = rtrim($contents, "\n")."\n\nHELPSCOUT_DOCS_API_KEY=\n";
        File::put($envPath, $append);

        $this->line('Added HELPSCOUT_DOCS_API_KEY= to .env');
    }

    private function ensureGitignoreLine(): void
    {
        $gitignorePath = base_path('.gitignore');

        if (! File::exists($gitignorePath)) {
            return;
        }

        $contents = File::get($gitignorePath);
        $storagePath = config('helpscout-tutorials.storage_path', 'helpscout-tutorials');
        $line = '/storage/'.ltrim($storagePath, '/');

        if (str_contains($contents, $line)) {
            return;
        }

        $append = rtrim($contents, "\n")."\n".$line."\n";
        File::put($gitignorePath, $append);

        $this->line("Added {$line} to .gitignore");
    }
}
