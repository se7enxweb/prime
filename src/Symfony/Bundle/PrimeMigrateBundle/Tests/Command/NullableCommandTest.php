<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeMigrateBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\PrimeMigrateBundle\Command\NullableCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for prime:migrate:nullable.
 *
 * Covers:
 *   - Read-only scan mode (default — no --fix)
 *   - Dry-run mode (--fix --dry-run)
 *   - Fix mode (--fix)
 *   - Safety: read-only scan NEVER writes files
 *   - Safety: dry-run NEVER writes files
 *   - Safety: fix only writes the specific changed files, not others
 *   - Safety: already-nullable files are not rewritten
 *   - --dry-run without --fix is a no-op (warning emitted)
 *
 * @author 7x <info@se7enx.com>
 */
class NullableCommandTest extends TestCase
{
    private Application $app;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $cmd       = new NullableCommand();
        $this->app = new Application();
        $this->app->add($cmd);
        $this->tester = new CommandTester($this->app->find('prime:migrate:nullable'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/prime_nullable_' . uniqid('', true);
        mkdir($dir, 0755, true);
        foreach ($files as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }
        return $dir;
    }

    private function removeDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($dir);
    }

    private function dirChecksums(string $dir): array
    {
        $checksums = [];
        foreach (glob($dir . '/*') ?: [] as $f) {
            if (is_file($f)) {
                $checksums[$f] = md5_file($f);
            }
        }
        ksort($checksums);
        return $checksums;
    }

    // ── Command meta ──────────────────────────────────────────────────────────

    public function testCommandName(): void
    {
        $this->assertSame('prime:migrate:nullable', (new NullableCommand())->getName());
    }

    // ── Read-only scan (no --fix) ─────────────────────────────────────────────

    public function testScanModeReturnsSuccessWhenNoIssues(): void
    {
        $dir  = $this->makeDir(['Clean.php' => '<?php class Clean { public function go(?string $x = null) {} }']);
        $code = $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testScanModeReturnsFailureWhenIssuesFound(): void
    {
        $dir  = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $code = $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(1, $code);
        $this->removeDir($dir);
    }

    public function testScanModeOutputContainsAffectedFile(): void
    {
        $dir = $this->makeDir(['MyController.php' => 'public function setFoo(DateTime $date = null) {}']);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('MyController.php', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testScanModeOutputContainsLineNumber(): void
    {
        $dir = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $date = null) {}']);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('Line 1:', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testScanModeOutputSuggestsFix(): void
    {
        $dir = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('--fix', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    /**
     * SAFETY: scan mode (no --fix) must NEVER write any file.
     */
    public function testScanModeNeverWritesAnyFile(): void
    {
        $dir    = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $before = $this->dirChecksums($dir);

        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);

        $after = $this->dirChecksums($dir);
        $this->assertSame($before, $after, 'Scan mode (no --fix) must not modify any file');
        $this->removeDir($dir);
    }

    // ── Dry-run mode (--fix --dry-run) ────────────────────────────────────────

    public function testDryRunModeIndicatesDryRunInOutput(): void
    {
        $dir = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);
        $this->assertStringContainsString('DRY RUN', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testDryRunModeShowsWouldBeMadeMessage(): void
    {
        $dir = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);
        $this->assertStringContainsString('would be made', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    /**
     * SAFETY: --fix --dry-run must NEVER write any file.
     */
    public function testDryRunNeverWritesAnyFile(): void
    {
        $dir    = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $before = $this->dirChecksums($dir);

        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);

        $after = $this->dirChecksums($dir);
        $this->assertSame($before, $after, '--fix --dry-run must not modify any file');
        $this->removeDir($dir);
    }

    public function testDryRunWithoutFixEmitsWarning(): void
    {
        $dir = $this->makeDir(['Clean.php' => '<?php class Clean {}']);
        $this->tester->execute(['--dir' => $dir, '--dry-run' => true], ['decorated' => false]);
        $this->assertStringContainsString('no effect', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    // ── Fix mode (--fix) ──────────────────────────────────────────────────────

    public function testFixModeRewritesImplicitNullableParam(): void
    {
        $dir = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $date = null) {}']);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $fixed = file_get_contents($dir . '/Bad.php');
        $this->assertStringContainsString('?DateTime', $fixed);
        $this->removeDir($dir);
    }

    public function testFixModeReturnsSuccess(): void
    {
        $dir  = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $code = $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testFixModeOutputConfirmsReplacement(): void
    {
        $dir = $this->makeDir(['Bad.php' => 'public function setFoo(DateTime $x = null) {}']);
        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $this->assertStringContainsString('[FIXED]', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testFixModeDoesNotTouchAlreadyNullableFiles(): void
    {
        $content = 'public function setFoo(?DateTime $date = null) {}';
        $dir     = $this->makeDir(['Clean.php' => $content]);
        $before  = $this->dirChecksums($dir);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $after = $this->dirChecksums($dir);
        $this->assertSame($before, $after, 'Already-nullable file must not be rewritten');
        $this->removeDir($dir);
    }

    public function testFixModeOnlyModifiesAffectedFiles(): void
    {
        $dir = $this->makeDir([
            'Bad.php'   => 'public function setFoo(DateTime $x = null) {}',
            'Clean.php' => '<?php class Clean { public function go(?string $x = null) {} }',
        ]);

        $cleanBefore = md5_file($dir . '/Clean.php');
        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $cleanAfter  = md5_file($dir . '/Clean.php');

        $this->assertSame($cleanBefore, $cleanAfter, 'Clean file must not be touched by --fix');
        $this->assertStringContainsString('?DateTime', file_get_contents($dir . '/Bad.php'));

        $this->removeDir($dir);
    }

    public function testFixModeSuccessWhenNoIssuesFound(): void
    {
        $dir  = $this->makeDir(['Clean.php' => '<?php class Clean {}']);
        $code = $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }
}
