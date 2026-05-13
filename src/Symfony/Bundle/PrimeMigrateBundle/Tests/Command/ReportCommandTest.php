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
use Symfony\Bundle\PrimeMigrateBundle\Command\ReportCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for prime:migrate:report.
 *
 * Covers:
 *   - text format (stdout): exit code semantics, SUMMARY section present
 *   - json format to file: valid JSON, expected top-level keys
 *   - html format to file: valid HTML, contains status badge, self-contained
 *   - html format without --output: exits with INVALID (2)
 *   - unknown format: exits with INVALID (2)
 *   - Safety: source files under scanned dir are never modified
 *
 * @author 7x <info@se7enx.com>
 */
class ReportCommandTest extends TestCase
{
    private Application $app;
    private CommandTester $tester;
    private string $tmpDir;

    protected function setUp(): void
    {
        $cmd       = new ReportCommand();
        $this->app = new Application();
        $this->app->add($cmd);
        $this->tester = new CommandTester($this->app->find('prime:migrate:report'));
        $this->tmpDir = sys_get_temp_dir() . '/prime_report_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tmpDir);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeSourceDir(array $files): string
    {
        $dir = $this->tmpDir . '/src';
        mkdir($dir, 0755, true);
        foreach ($files as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }
        return $dir;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    private function dirChecksums(string $dir): array
    {
        $checksums = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $f) {
            if ($f->isFile()) {
                $checksums[$f->getPathname()] = md5_file($f->getPathname());
            }
        }
        ksort($checksums);
        return $checksums;
    }

    // ── Command meta ──────────────────────────────────────────────────────────

    public function testCommandName(): void
    {
        $this->assertSame('prime:migrate:report', (new ReportCommand())->getName());
    }

    // ── Text format ───────────────────────────────────────────────────────────

    public function testTextFormatDefaultSuccessWhenClean(): void
    {
        $src  = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $code = $this->tester->execute(['--dir' => $src], ['decorated' => false]);
        $this->assertSame(0, $code);
    }

    public function testTextFormatReturnsFailureWhenIssues(): void
    {
        $src  = $this->makeSourceDir(['Bad.php' => 'public function setFoo(DateTime' . ' $x = null) {}']);
        $code = $this->tester->execute(['--dir' => $src], ['decorated' => false]);
        $this->assertSame(1, $code);
    }

    public function testTextFormatOutputContainsSummarySection(): void
    {
        $src = $this->makeSourceDir(['Bad.php' => 'public function setFoo(DateTime' . ' $x = null) {}']);
        $this->tester->execute(['--dir' => $src], ['decorated' => false]);
        $this->assertStringContainsString('SUMMARY', $this->tester->getDisplay());
    }

    public function testTextFormatOutputContainsScanSections(): void
    {
        $src = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $this->tester->execute(['--dir' => $src], ['decorated' => false]);
        $out = $this->tester->getDisplay();
        $this->assertStringContainsString('nullable', strtolower($out));
        $this->assertStringContainsString('form type', strtolower($out));
    }

    // ── JSON format ───────────────────────────────────────────────────────────

    public function testJsonFormatWritesValidJsonFile(): void
    {
        $src     = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $outFile = $this->tmpDir . '/report.json';

        $code = $this->tester->execute(
            ['--dir' => $src, '--format' => 'json', '--output' => $outFile],
            ['decorated' => false]
        );

        $this->assertSame(0, $code);
        $this->assertFileExists($outFile);

        $decoded = json_decode(file_get_contents($outFile), true);
        $this->assertIsArray($decoded, 'JSON report must be a valid JSON object');
    }

    public function testJsonFormatContainsExpectedTopLevelKeys(): void
    {
        $src     = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $outFile = $this->tmpDir . '/report.json';
        $this->tester->execute(
            ['--dir' => $src, '--format' => 'json', '--output' => $outFile],
            ['decorated' => false]
        );

        $decoded = json_decode(file_get_contents($outFile), true);
        $this->assertArrayHasKey('generated', $decoded);
        $this->assertArrayHasKey('scans', $decoded);
        $this->assertArrayHasKey('total_issues', $decoded);
    }

    public function testJsonFormatToStdout(): void
    {
        $src  = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $code = $this->tester->execute(
            ['--dir' => $src, '--format' => 'json'],
            ['decorated' => false]
        );
        $this->assertSame(0, $code);
        // The command emits "Scanning ..." before the JSON block;
        // strip everything before the first '{' to isolate the JSON.
        $raw     = $this->tester->getDisplay();
        $jsonStart = strpos($raw, '{');
        $this->assertNotFalse($jsonStart, 'Output should contain a JSON object');
        $decoded = json_decode(substr($raw, $jsonStart), true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('generated', $decoded);
    }

    // ── HTML format ───────────────────────────────────────────────────────────

    public function testHtmlFormatWritesHtmlFile(): void
    {
        $src     = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $outFile = $this->tmpDir . '/report.html';

        $code = $this->tester->execute(
            ['--dir' => $src, '--format' => 'html', '--output' => $outFile],
            ['decorated' => false]
        );

        $this->assertSame(0, $code);
        $this->assertFileExists($outFile);
        $html = file_get_contents($outFile);
        $this->assertStringContainsString('<html', $html);
    }

    public function testHtmlFormatContainsStatusBadge(): void
    {
        $src     = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $outFile = $this->tmpDir . '/report.html';
        $this->tester->execute(
            ['--dir' => $src, '--format' => 'html', '--output' => $outFile],
            ['decorated' => false]
        );
        $html = file_get_contents($outFile);
        $this->assertMatchesRegularExpression('/PASS|FAIL|status/i', $html);
    }

    public function testHtmlFormatIsSelfContained(): void
    {
        $src     = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $outFile = $this->tmpDir . '/report.html';
        $this->tester->execute(
            ['--dir' => $src, '--format' => 'html', '--output' => $outFile],
            ['decorated' => false]
        );
        $html = file_get_contents($outFile);
        // Should contain inline <style> — no external stylesheets required
        $this->assertStringContainsString('<style', $html);
        $this->assertStringNotContainsString('<link rel="stylesheet"', $html);
    }

    public function testHtmlFormatWithoutOutputReturnsInvalid(): void
    {
        $src  = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $code = $this->tester->execute(
            ['--dir' => $src, '--format' => 'html'],
            ['decorated' => false]
        );
        $this->assertSame(2, $code);
    }

    // ── Invalid format ────────────────────────────────────────────────────────

    public function testUnknownFormatReturnsInvalid(): void
    {
        $src  = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $code = $this->tester->execute(
            ['--dir' => $src, '--format' => 'csv'],
            ['decorated' => false]
        );
        $this->assertSame(2, $code);
    }

    public function testUnknownFormatOutputExplainsError(): void
    {
        $src = $this->makeSourceDir(['Clean.php' => '<?php class Clean {}']);
        $this->tester->execute(
            ['--dir' => $src, '--format' => 'csv'],
            ['decorated' => false]
        );
        $this->assertStringContainsString('format', strtolower($this->tester->getDisplay()));
    }

    // ── Safety: source files are never modified by report ────────────────────

    /**
     * SAFETY: the report command must never write to the scanned source directory.
     * Report output goes to --output file or stdout — never back into src/.
     */
    public function testReportCommandNeverModifiesSourceFiles(): void
    {
        $src = $this->makeSourceDir([
            'Bad.php'  => 'public function setFoo(DateTime' . ' $x = null) {}',
            'Form.php' => "->add('name', 'text')",
        ]);
        $outFile = $this->tmpDir . '/report.json';

        $before = $this->dirChecksums($src);
        $this->tester->execute(
            ['--dir' => $src, '--format' => 'json', '--output' => $outFile],
            ['decorated' => false]
        );
        $after = $this->dirChecksums($src);

        $this->assertSame($before, $after, 'prime:migrate:report must not modify source files');
    }
}
