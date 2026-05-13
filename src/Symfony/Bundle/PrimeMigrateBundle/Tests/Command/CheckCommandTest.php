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
use Symfony\Bundle\PrimeMigrateBundle\Command\CheckCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for prime:migrate:check.
 *
 * @author 7x <info@se7enx.com>
 */
class CheckCommandTest extends TestCase
{
    private Application $app;
    private CheckCommand $command;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->command = new CheckCommand();
        $this->app     = new Application();
        $this->app->add($this->command);
        $this->tester  = new CommandTester($this->app->find('prime:migrate:check'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/prime_check_' . uniqid('', true);
        mkdir($dir, 0755, true);
        foreach ($files as $name => $content) {
            $sub = dirname($dir . '/' . $name);
            if (!is_dir($sub)) {
                mkdir($sub, 0755, true);
            }
            file_put_contents($dir . '/' . $name, $content);
        }
        return $dir;
    }

    private function removeDir(string $dir): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function testCommandIsRegistered(): void
    {
        $this->assertSame('prime:migrate:check', $this->command->getName());
    }

    public function testReturnsSuccessWhenNoIssues(): void
    {
        $dir = $this->makeDir(['Clean.php' => '<?php class Clean {}']);
        $exitCode = $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(0, $exitCode);
        $this->removeDir($dir);
    }

    public function testReturnsFailureWhenIssuesFound(): void
    {
        $dir = $this->makeDir([
            'Bad.php' => 'public function setFoo(DateTime' . ' $x = null) {}',
        ]);
        $exitCode = $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(1, $exitCode);
        $this->removeDir($dir);
    }

    public function testOutputContainsScanRows(): void
    {
        $dir = $this->makeDir(['Clean.php' => '<?php class Clean {}']);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $output = $this->tester->getDisplay();

        $this->assertStringContainsString('[SCAN] Implicit nullable types', $output);
        $this->assertStringContainsString('[SCAN] String form type names', $output);
        $this->assertStringContainsString('[SCAN] Reserved constraint names', $output);
        $this->assertStringContainsString('[SCAN] YAML !php/object usage', $output);
        $this->assertStringContainsString('[SCAN] Twig_* class references', $output);

        $this->removeDir($dir);
    }

    public function testOutputContainsHeading(): void
    {
        $dir = $this->makeDir(['Clean.php' => '<?php']);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('7x Prime Migration Check', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testVerboseModeShowsAffectedLines(): void
    {
        $dir = $this->makeDir([
            'Bad.php' => 'public function setFoo(DateTime' . ' $date = null) {}',
        ]);
        $this->tester->execute(
            ['--dir' => $dir],
            ['decorated' => false, 'verbosity' => \Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE]
        );
        $output = $this->tester->getDisplay();
        $this->assertStringContainsString('setFoo', $output);
        $this->removeDir($dir);
    }

    public function testInvalidDirThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->tester->execute(['--dir' => '/path/that/does/not/exist/ever'], ['decorated' => false]);
    }

    public function testSuccessOutputSaysNoIssues(): void
    {
        $dir = $this->makeDir(['OK.php' => '<?php class OK {}']);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('no issues', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testTotalLineAppearsWhenIssuesFound(): void
    {
        $dir = $this->makeDir([
            'Bad.php' => 'public function setFoo(DateTime' . ' $x = null) {}',
        ]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('Total:', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    /**
     * SAFETY: check command must never write any file regardless of the content
     * it finds in the scanned directory.
     */
    public function testCheckCommandNeverWritesFiles(): void
    {
        $dir  = $this->makeDir([
            'Bad.php'  => 'public function setFoo(DateTime' . ' $x = null) {}',
            'Form.php' => "->add('name', 'text')",
        ]);

        $before = $this->dirChecksums($dir);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $after = $this->dirChecksums($dir);

        $this->assertSame($before, $after, 'prime:migrate:check must not modify any file on disk');
        $this->removeDir($dir);
    }

    private function dirChecksums(string $dir): array
    {
        $checksums = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile()) {
                $checksums[$f->getPathname()] = md5_file($f->getPathname());
            }
        }
        ksort($checksums);
        return $checksums;
    }
}
