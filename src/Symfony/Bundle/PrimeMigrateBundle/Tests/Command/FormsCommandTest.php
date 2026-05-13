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
use Symfony\Bundle\PrimeMigrateBundle\Command\FormsCommand;
use Symfony\Bundle\PrimeMigrateBundle\Command\ScannerTrait;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for prime:migrate:forms.
 *
 * Covers:
 *   - Read-only scan mode (default — no --fix)
 *   - Dry-run mode (--fix --dry-run)
 *   - Fix mode (--fix)
 *   - Safety: read-only scan NEVER writes files
 *   - Safety: dry-run NEVER writes files
 *   - Safety: fix only writes the specific changed files, not others
 *   - Safety: already-FQCN files are not rewritten
 *   - --dry-run without --fix is a no-op (warning emitted)
 *   - Unit tests for applyFormsFix() and formTypeNamespaceMap()
 *
 * @author 7x <info@se7enx.com>
 */
class FormsCommandTest extends TestCase
{
    private Application $app;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $cmd       = new FormsCommand();
        $this->app = new Application();
        $this->app->add($cmd);
        $this->tester = new CommandTester($this->app->find('prime:migrate:forms'));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/prime_forms_' . uniqid('', true);
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
        $this->assertSame('prime:migrate:forms', (new FormsCommand())->getName());
    }

    // ── Read-only scan (no --fix) ─────────────────────────────────────────────

    public function testScanModeReturnsSuccessWhenNoAliases(): void
    {
        $dir  = $this->makeDir(['Clean.php' => "->add('name', TextType::class)"]);
        $code = $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testScanModeReturnsFailureWhenAliasesFound(): void
    {
        $dir  = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $code = $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(1, $code);
        $this->removeDir($dir);
    }

    public function testScanModeOutputContainsAffectedFile(): void
    {
        $dir = $this->makeDir(['ArticleType.php' => "->add('title', 'text')"]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('ArticleType.php', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testScanModeOutputContainsLineNumber(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('Line 1:', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testScanModeOutputContainsFqcnSuggestion(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('TextType::class', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testScanModeOutputSuggestsFixCommand(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('--fix', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testScanModeOutputReferencesMigrationDoc(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('MIGRATION.md', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    /**
     * SAFETY: scan mode (no --fix) must NEVER write any file.
     */
    public function testScanModeNeverWritesAnyFile(): void
    {
        $dir    = $this->makeDir(['Form.php' => "->add('name', 'text')\n->add('email', 'email')"]);
        $before = $this->dirChecksums($dir);

        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);

        $after = $this->dirChecksums($dir);
        $this->assertSame($before, $after, 'Scan mode (no --fix) must not modify any file');
        $this->removeDir($dir);
    }

    // ── Dry-run mode (--fix --dry-run) ────────────────────────────────────────

    public function testDryRunModeIndicatesDryRunInOutput(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);
        $this->assertStringContainsString('DRY RUN', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testDryRunModeShowsWouldBeMadeMessage(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);
        $this->assertStringContainsString('would be made', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testDryRunModeShowsDiffLines(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);
        $out = $this->tester->getDisplay();
        $this->assertStringContainsString("'text'", $out);
        $this->assertStringContainsString('TextType::class', $out);
        $this->removeDir($dir);
    }

    /**
     * SAFETY: --fix --dry-run must NEVER write any file.
     */
    public function testDryRunNeverWritesAnyFile(): void
    {
        $dir    = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $before = $this->dirChecksums($dir);

        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);

        $after = $this->dirChecksums($dir);
        $this->assertSame($before, $after, '--fix --dry-run must not modify any file');
        $this->removeDir($dir);
    }

    public function testDryRunWithoutFixEmitsWarning(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', TextType::class)"]);
        $this->tester->execute(['--dir' => $dir, '--dry-run' => true], ['decorated' => false]);
        $this->assertStringContainsString('no effect', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    // ── Fix mode (--fix) ──────────────────────────────────────────────────────

    public function testFixModeReplacesStringAliasWithFqcn(): void
    {
        $dir = $this->makeDir(['Form.php' => "<?php\nclass F {\n    public function b(\$b) { \$b->add('name', 'text'); }\n}\n"]);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $fixed = file_get_contents($dir . '/Form.php');
        $this->assertStringContainsString('TextType::class', $fixed);
        $this->assertStringNotContainsString("'text'", $fixed);
        $this->removeDir($dir);
    }

    public function testFixModeInjectsUseStatement(): void
    {
        $dir = $this->makeDir(['Form.php' => "<?php\nnamespace App\\Form;\n\nclass F {\n    public function b(\$b) { \$b->add('name', 'text'); }\n}\n"]);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $fixed = file_get_contents($dir . '/Form.php');
        $this->assertStringContainsString('use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;', $fixed);
        $this->removeDir($dir);
    }

    public function testFixModeDoesNotDuplicateExistingUseStatement(): void
    {
        $src = "<?php\nnamespace App\\Form;\nuse Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;\n\nclass F {\n    public function b(\$b) { \$b->add('name', 'text'); }\n}\n";
        $dir = $this->makeDir(['Form.php' => $src]);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $fixed = file_get_contents($dir . '/Form.php');
        $count = substr_count($fixed, 'use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;');
        $this->assertSame(1, $count, 'Existing use statement must not be duplicated');
        $this->removeDir($dir);
    }

    public function testFixModeReturnsSuccess(): void
    {
        $dir  = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $code = $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testFixModeOutputConfirmsReplacement(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $this->assertStringContainsString('[FIXED]', $this->tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testFixModeDoesNotTouchAlreadyFqcnFiles(): void
    {
        $content = "->add('name', TextType::class)";
        $dir     = $this->makeDir(['Form.php' => $content]);
        $before  = $this->dirChecksums($dir);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $after = $this->dirChecksums($dir);
        $this->assertSame($before, $after, 'Already-FQCN file must not be rewritten');
        $this->removeDir($dir);
    }

    public function testFixModeOnlyModifiesAffectedFiles(): void
    {
        $dir = $this->makeDir([
            'Bad.php'   => "->add('name', 'text')",
            'Clean.php' => "->add('name', TextType::class)",
        ]);

        $cleanBefore = md5_file($dir . '/Clean.php');
        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $cleanAfter  = md5_file($dir . '/Clean.php');

        $this->assertSame($cleanBefore, $cleanAfter, 'Clean file must not be touched by --fix');
        $this->assertStringContainsString('TextType::class', file_get_contents($dir . '/Bad.php'));

        $this->removeDir($dir);
    }

    public function testFixModeSuccessWhenNoIssuesFound(): void
    {
        $dir  = $this->makeDir(['Form.php' => "->add('name', TextType::class)"]);
        $code = $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testFixModeHandlesMultipleAliasesInOneFile(): void
    {
        $src = "<?php\nclass F {\n    public function b(\$b) {\n        \$b->add('name', 'text');\n        \$b->add('email', 'email');\n        \$b->add('note', 'textarea');\n    }\n}\n";
        $dir = $this->makeDir(['Form.php' => $src]);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $fixed = file_get_contents($dir . '/Form.php');
        $this->assertStringContainsString('TextType::class', $fixed);
        $this->assertStringContainsString('EmailType::class', $fixed);
        $this->assertStringContainsString('TextareaType::class', $fixed);
        $this->assertStringContainsString('use Symfony\\Component\\Form\\Extension\\Core\\Type\\EmailType;', $fixed);
        $this->assertStringContainsString('use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextareaType;', $fixed);
        $this->assertStringContainsString('use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;', $fixed);
        $this->removeDir($dir);
    }

    // ── Unit: applyFormsFix() ─────────────────────────────────────────────────

    public function testApplyFormsFixReplacesAlias(): void
    {
        $content = "<?php\n\$b->add('name', 'text', []);\n";

        $class = new class {
            use ScannerTrait;
            public function run(string $c): array { return self::applyFormsFix($c); }
        };

        $result = $class->run($content);
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('TextType::class', $result['fixed']);
        $this->assertStringNotContainsString("'text'", $result['fixed']);
    }

    public function testApplyFormsFixDoesNotDoubleCountFieldNameAlias(): void
    {
        // ->add('email', 'email') — 'email' field name must NOT be replaced, only the type
        $content = "<?php\n\$b->add('email', 'email');\n";

        $class = new class {
            use ScannerTrait;
            public function run(string $c): array { return self::applyFormsFix($c); }
        };

        $result = $class->run($content);
        $this->assertSame(1, $result['count'], 'Only the type alias position should be replaced');
        $fixed = $result['fixed'];
        // field name 'email' stays as a string literal
        $this->assertStringContainsString("'email'", $fixed);
        // type alias is replaced
        $this->assertStringContainsString('EmailType::class', $fixed);
    }

    public function testApplyFormsFixReturnsInjectedUseStatements(): void
    {
        $content = "<?php\nnamespace App\\Form;\n\n\$b->add('name', 'text', []);\n";

        $class = new class {
            use ScannerTrait;
            public function run(string $c): array { return self::applyFormsFix($c); }
        };

        $result = $class->run($content);
        $this->assertArrayHasKey('injected', $result);
        $this->assertContains('Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', $result['injected']);
    }

    public function testApplyFormsFixInjectsUseAfterNamespace(): void
    {
        $content = "<?php\nnamespace App\\Form;\n\n\$b->add('name', 'text', []);\n";

        $class = new class {
            use ScannerTrait;
            public function run(string $c): array { return self::applyFormsFix($c); }
        };

        $result = $class->run($content);
        $fixed  = $result['fixed'];

        // use statement must appear after the namespace line
        $nsPos  = strpos($fixed, 'namespace App\\Form;');
        $usePos = strpos($fixed, 'use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;');
        $this->assertNotFalse($usePos);
        $this->assertGreaterThan($nsPos, $usePos);
    }

    public function testApplyFormsFixZeroCountWhenNoAliases(): void
    {
        $content = "<?php\n\$b->add('name', TextType::class);\n";

        $class = new class {
            use ScannerTrait;
            public function run(string $c): array { return self::applyFormsFix($c); }
        };

        $result = $class->run($content);
        $this->assertSame(0, $result['count']);
        $this->assertSame($content, $result['fixed']);
    }

    // ── Unit: formTypeNamespaceMap() ──────────────────────────────────────────

    public function testFormTypeNamespaceMapContainsCoreTypes(): void
    {
        $class = new class {
            use ScannerTrait;
            public function run(): array { return self::formTypeNamespaceMap(); }
        };

        $map = $class->run();
        $this->assertArrayHasKey('text', $map);
        $this->assertSame('Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', $map['text']);
    }

    public function testFormTypeNamespaceMapEntityUsesDoctrineNamespace(): void
    {
        $class = new class {
            use ScannerTrait;
            public function run(): array { return self::formTypeNamespaceMap(); }
        };

        $map = $class->run();
        $this->assertArrayHasKey('entity', $map);
        $this->assertSame('Symfony\\Bridge\\Doctrine\\Form\\Type\\EntityType', $map['entity']);
    }

    public function testFormTypeNamespaceMapHasSameKeysAsFormTypeMap(): void
    {
        $class = new class {
            use ScannerTrait;
            public function runNs(): array  { return self::formTypeNamespaceMap(); }
            public function runMap(): array { return self::formTypeMap(); }
        };

        $this->assertSame(array_keys($class->runMap()), array_keys($class->runNs()));
    }

    // ── Fix mode — additional edge cases ──────────────────────────────────────

    public function testFixModeHandlesEntityTypeUsingDoctrineNamespace(): void
    {
        $src = "<?php\nclass F {\n    public function b(\$b) { \$b->add('org', 'entity', ['class' => Org::class]); }\n}\n";
        $dir = $this->makeDir(['Form.php' => $src]);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $fixed = file_get_contents($dir . '/Form.php');
        $this->assertStringContainsString('EntityType::class', $fixed);
        $this->assertStringContainsString('use Symfony\\Bridge\\Doctrine\\Form\\Type\\EntityType;', $fixed);
        $this->removeDir($dir);
    }

    public function testFixModeIsIdempotent(): void
    {
        $src = "<?php\nclass F {\n    public function b(\$b) { \$b->add('name', 'text'); }\n}\n";
        $dir = $this->makeDir(['Form.php' => $src]);

        // First pass
        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $afterFirst = file_get_contents($dir . '/Form.php');

        // Second pass — the file is already clean; should not be modified
        $cksumBefore = md5_file($dir . '/Form.php');
        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);
        $cksumAfter  = md5_file($dir . '/Form.php');

        $this->assertSame($cksumBefore, $cksumAfter, '--fix applied twice must not alter the already-fixed file');
        $this->removeDir($dir);
    }

    public function testDryRunModeReturnsSuccessExitCode(): void
    {
        $dir  = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $code = $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testDryRunModeShowsUseStatementsThatWouldBeAdded(): void
    {
        $src = "<?php\nnamespace App\\Form;\n\nclass F {\n    public function b(\$b) { \$b->add('name', 'text'); }\n}\n";
        $dir = $this->makeDir(['Form.php' => $src]);

        $this->tester->execute(['--dir' => $dir, '--fix' => true, '--dry-run' => true], ['decorated' => false]);

        $out = $this->tester->getDisplay();
        $this->assertStringContainsString('use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;', $out);
        $this->removeDir($dir);
    }

    public function testScanModeCountsSummaryLine(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')\n->add('note', 'textarea')"]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $out = $this->tester->getDisplay();
        $this->assertMatchesRegularExpression('/Found \d+ string type/', $out);
        $this->removeDir($dir);
    }

    public function testScanModeOutputContainsIssueCount(): void
    {
        $dir = $this->makeDir(['Form.php' => "->add('name', 'text')\n->add('note', 'textarea')"]);
        $this->tester->execute(['--dir' => $dir], ['decorated' => false]);
        $out = $this->tester->getDisplay();
        $this->assertStringContainsString('2', $out);
        $this->removeDir($dir);
    }

    public function testFixModeDoesNotFlagFieldNameAsExtraIssue(): void
    {
        // ->add('email', 'email') — only the type alias should be fixed; 1 replacement expected
        $src = "<?php\nclass F { public function b(\$b) { \$b->add('email', 'email'); } }\n";
        $dir = $this->makeDir(['Form.php' => $src]);

        $this->tester->execute(['--dir' => $dir, '--fix' => true], ['decorated' => false]);

        $fixed = file_get_contents($dir . '/Form.php');
        // field name must remain as a string literal
        $this->assertStringContainsString("'email'", $fixed);
        // type position must be FQCN
        $this->assertStringContainsString('EmailType::class', $fixed);
        // must show exactly 1 replacement in output
        $this->assertStringContainsString('1 replacement', $this->tester->getDisplay());
        $this->removeDir($dir);
    }
}
