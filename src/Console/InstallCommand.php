<?php

namespace Wndp\HelpScoutTutorials\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'tutorials:install
        {--force : Overwrite existing files when publishing}';

    protected $description = 'Publish config, link Claude Code skills, and tweak .env / .gitignore for HelpScout tutorials.';

    public function handle(): int
    {
        $this->publishConfig();
        $this->linkClaudeSkills();
        $this->removeLegacyCommands();
        $this->ensureEnvLine();
        $this->ensureGitignoreLine();

        $this->info('helpscout-tutorials install complete.');
        $this->line(' - Set HELPSCOUT_DOCS_API_KEY in your .env if you have not already.');
        $this->line(' - Restart Claude Code for the /tutorials-* skills to appear.');

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

    /*
     * Symlink each packaged skill into .claude/skills/ with a relative link, so
     * Claude Code reads them straight from vendor/ and they track the installed
     * package version without being copied into the app.
     */
    private function linkClaudeSkills(): void
    {
        $source = realpath(__DIR__.'/../../resources/claude-skills');
        $target = base_path('.claude/skills');

        File::ensureDirectoryExists($target);

        foreach (File::directories($source) as $skillPath) {
            $name = basename($skillPath);
            $link = $target.'/'.$name;

            if (is_link($link)) {
                unlink($link);
            } elseif (file_exists($link)) {
                if (! $this->option('force')) {
                    $this->warn("Skipped .claude/skills/{$name}: a real directory already exists there (use --force to replace it).");

                    continue;
                }

                File::deleteDirectory($link);
            }

            if (@symlink($this->relativePath($target, $skillPath), $link)) {
                continue;
            }

            // Symlinks can be unavailable (e.g. Windows without developer mode).
            File::copyDirectory($skillPath, $link);
            $this->warn("Could not symlink .claude/skills/{$name}; copied it instead. Re-run tutorials:install after updating the package.");
        }

        $this->removeStaleSkillLinks($target);
        $this->line('Linked Claude Code skills into .claude/skills/');
    }

    /*
     * Remove links that point into this package's skills directory but whose
     * skill no longer exists (renamed or removed in a package update).
     */
    private function removeStaleSkillLinks(string $target): void
    {
        foreach (glob($target.'/*') ?: [] as $link) {
            if (! is_link($link) || file_exists($link)) {
                continue;
            }

            if (str_contains(readlink($link), 'resources/claude-skills/')) {
                unlink($link);
            }
        }
    }

    /*
     * Releases before 0.3.0 copied the templates into .claude/commands/. They
     * would now shadow the linked skills with stale copies.
     */
    private function removeLegacyCommands(): void
    {
        $legacy = array_filter(
            array_map(
                fn (string $skillPath) => base_path('.claude/commands/'.basename($skillPath).'.md'),
                File::directories(__DIR__.'/../../resources/claude-skills'),
            ),
            fn (string $path) => File::exists($path),
        );

        if ($legacy === []) {
            return;
        }

        if (! $this->confirm('Remove '.count($legacy).' old tutorials-*.md copies from .claude/commands/? They are replaced by the linked skills.', true)) {
            return;
        }

        File::delete($legacy);
        $this->line('Removed old copies from .claude/commands/');
    }

    private function relativePath(string $from, string $to): string
    {
        $from = explode('/', trim(realpath($from), '/'));
        $to = explode('/', trim(realpath($to), '/'));

        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)).implode('/', $to);
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
