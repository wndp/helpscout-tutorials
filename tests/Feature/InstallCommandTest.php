<?php

namespace Wndp\HelpScoutTutorials\Tests\Feature;

use Illuminate\Support\Facades\File;
use Wndp\HelpScoutTutorials\Tests\TestCase;

class InstallCommandTest extends TestCase
{
    private string $skillsPath;

    private string $commandsPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skillsPath = base_path('.claude/skills');
        $this->commandsPath = base_path('.claude/commands');
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();

        parent::tearDown();
    }

    public function test_links_each_skill_with_a_relative_symlink(): void
    {
        $this->artisan('tutorials:install')->assertSuccessful();

        $link = $this->skillsPath.'/tutorials-review';

        $this->assertTrue(is_link($link));
        $this->assertStringStartsWith('../', readlink($link));
        $this->assertFileExists($link.'/SKILL.md');
        $this->assertStringContainsString('name: tutorials-review', File::get($link.'/SKILL.md'));
        $this->assertCount(7, glob($this->skillsPath.'/tutorials-*'));
    }

    public function test_rerunning_replaces_existing_links(): void
    {
        $this->artisan('tutorials:install')->assertSuccessful();
        $this->artisan('tutorials:install')->assertSuccessful();

        $this->assertTrue(is_link($this->skillsPath.'/tutorials-review'));
    }

    public function test_skips_a_real_directory_unless_forced(): void
    {
        File::ensureDirectoryExists($this->skillsPath.'/tutorials-review');
        File::put($this->skillsPath.'/tutorials-review/SKILL.md', 'local override');

        $this->artisan('tutorials:install')
            ->expectsOutputToContain('Skipped .claude/skills/tutorials-review')
            ->assertSuccessful();

        $this->assertFalse(is_link($this->skillsPath.'/tutorials-review'));

        $this->artisan('tutorials:install', ['--force' => true])->assertSuccessful();

        $this->assertTrue(is_link($this->skillsPath.'/tutorials-review'));
    }

    public function test_removes_dangling_links_into_the_package(): void
    {
        File::ensureDirectoryExists($this->skillsPath);
        symlink('../../vendor/wndp/helpscout-tutorials/resources/claude-skills/tutorials-retired', $this->skillsPath.'/tutorials-retired');
        symlink('../../somewhere-else/my-skill', $this->skillsPath.'/my-skill');

        $this->artisan('tutorials:install')->assertSuccessful();

        $this->assertFalse(is_link($this->skillsPath.'/tutorials-retired'));
        $this->assertTrue(is_link($this->skillsPath.'/my-skill'));
    }

    public function test_offers_to_remove_legacy_command_copies(): void
    {
        File::ensureDirectoryExists($this->commandsPath);
        File::put($this->commandsPath.'/tutorials-review.md', 'old copy');
        File::put($this->commandsPath.'/my-own-command.md', 'keep me');

        $this->artisan('tutorials:install')
            ->expectsConfirmation('Remove 1 old tutorials-*.md copies from .claude/commands/? They are replaced by the linked skills.', 'yes')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->commandsPath.'/tutorials-review.md');
        $this->assertFileExists($this->commandsPath.'/my-own-command.md');
    }

    private function cleanUp(): void
    {
        foreach (glob($this->skillsPath.'/*') ?: [] as $entry) {
            is_link($entry) ? unlink($entry) : File::deleteDirectory($entry);
        }

        File::deleteDirectory(base_path('.claude'));
    }
}
