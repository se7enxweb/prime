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
use Symfony\Bundle\PrimeMigrateBundle\Command\ConstraintsCommand;
use Symfony\Bundle\PrimeMigrateBundle\Command\YamlCommand;
use Symfony\Bundle\PrimeMigrateBundle\Command\TwigCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Integration tests for the four read-only report commands:
 *   prime:migrate:forms
 *   prime:migrate:constraints
 *   prime:migrate:yaml
 *   prime:migrate:twig
 *
 * All four commands are strictly report-only — they must NEVER write, modify,
 * or delete any file, regardless of what they find. This is explicitly proven
 * by the safety tests below.
 *
 * @author 7x <info@se7enx.com>
 */
class ReadOnlyCommandsTest extends TestCase
{
    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/prime_ro_' . uniqid('', true);
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

    private function testerFor(object $cmd): CommandTester
    {
        $app = new Application();
        $app->add($cmd);
        return new CommandTester($app->find($cmd->getName()));
    }

    // ════════════════════════════════════════════════════════════════════════
    // prime:migrate:forms
    // ════════════════════════════════════════════════════════════════════════

    public function testFormsCommandName(): void
    {
        $this->assertSame('prime:migrate:forms', (new FormsCommand())->getName());
    }

    public function testFormsCommandSuccessWhenNoAliases(): void
    {
        $dir    = $this->makeDir(['OK.php' => "->add('name', TextType::class)"]);
        $tester = $this->testerFor(new FormsCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->assertStringContainsString('FQCN', $tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testFormsCommandDetectsStringAlias(): void
    {
        $dir    = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $tester = $this->testerFor(new FormsCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString("'text'", $tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testFormsCommandOutputContainsFqcnSuggestion(): void
    {
        $dir    = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $tester = $this->testerFor(new FormsCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('TextType::class', $tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testFormsCommandOutputReferencesMigrationDoc(): void
    {
        $dir    = $this->makeDir(['Form.php' => "->add('name', 'text')"]);
        $tester = $this->testerFor(new FormsCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('MIGRATION.md', $tester->getDisplay());
        $this->removeDir($dir);
    }

    /**
     * SAFETY: forms scan mode (no --fix) must never write any file.
     * The --fix mode is tested separately in FormsCommandTest.
     */
    public function testFormsCommandScanModeNeverWritesFiles(): void
    {
        $dir    = $this->makeDir(['Form.php' => "->add('name', 'text')\n->add('email', 'email')"]);
        $before = $this->dirChecksums($dir);
        $tester = $this->testerFor(new FormsCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame($before, $this->dirChecksums($dir), 'prime:migrate:forms scan mode (no --fix) must not write any file');
        $this->removeDir($dir);
    }

    // ════════════════════════════════════════════════════════════════════════
    // prime:migrate:constraints
    // ════════════════════════════════════════════════════════════════════════

    public function testConstraintsCommandName(): void
    {
        $this->assertSame('prime:migrate:constraints', (new ConstraintsCommand())->getName());
    }

    public function testConstraintsCommandSuccessWhenNoIssues(): void
    {
        $dir    = $this->makeDir(['Good.php' => 'new Constraints\\IsTrue()']);
        $tester = $this->testerFor(new ConstraintsCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testConstraintsCommandDetectsTrueConstraint(): void
    {
        $dir    = $this->makeDir(['Bad.php' => 'use Symfony\\Component\\Validator\\Constraints\\True;']);
        $tester = $this->testerFor(new ConstraintsCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(1, $code);
        $this->removeDir($dir);
    }

    public function testConstraintsCommandOutputShowsMigrationPaths(): void
    {
        $dir    = $this->makeDir(['Bad.php' => 'new Constraints\\True()']);
        $tester = $this->testerFor(new ConstraintsCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $out = $tester->getDisplay();
        $this->assertStringContainsString('IsTrue', $out);
        $this->assertStringContainsString('IsFalse', $out);
        $this->assertStringContainsString('IsNull', $out);
        $this->removeDir($dir);
    }

    /**
     * SAFETY: constraints command must never write any file.
     */
    public function testConstraintsCommandNeverWritesFiles(): void
    {
        $dir    = $this->makeDir(['Bad.php' => 'new Constraints\\True()']);
        $before = $this->dirChecksums($dir);
        $tester = $this->testerFor(new ConstraintsCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame($before, $this->dirChecksums($dir), 'prime:migrate:constraints must not write any file');
        $this->removeDir($dir);
    }

    // ════════════════════════════════════════════════════════════════════════
    // prime:migrate:yaml
    // ════════════════════════════════════════════════════════════════════════

    public function testYamlCommandName(): void
    {
        $this->assertSame('prime:migrate:yaml', (new YamlCommand())->getName());
    }

    public function testYamlCommandSuccessWhenNoPhpObjectTags(): void
    {
        $dir    = $this->makeDir(['config.yml' => "database:\n  host: 127.0.0.1\n"]);
        $tester = $this->testerFor(new YamlCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testYamlCommandDetectsPhpObjectTag(): void
    {
        $dir    = $this->makeDir(['fixtures.yml' => "obj: !php/object: \"O:4:\\\"User\\\":0:{}\"" ]);
        $tester = $this->testerFor(new YamlCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('fixtures.yml', $tester->getDisplay());
        $this->removeDir($dir);
    }

    public function testYamlCommandOutputWarnsAboutDeserialisation(): void
    {
        $dir    = $this->makeDir(['f.yml' => "obj: !php/object: \"O:0:{}\""]);
        $tester = $this->testerFor(new YamlCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertStringContainsString('no longer', $tester->getDisplay());
        $this->removeDir($dir);
    }

    /**
     * SAFETY: yaml command must never write any file.
     */
    public function testYamlCommandNeverWritesFiles(): void
    {
        $dir    = $this->makeDir(['f.yml' => "obj: !php/object: \"O:0:{}\""]);
        $before = $this->dirChecksums($dir);
        $tester = $this->testerFor(new YamlCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame($before, $this->dirChecksums($dir), 'prime:migrate:yaml must not write any file');
        $this->removeDir($dir);
    }

    // ════════════════════════════════════════════════════════════════════════
    // prime:migrate:twig
    // ════════════════════════════════════════════════════════════════════════

    public function testTwigCommandName(): void
    {
        $this->assertSame('prime:migrate:twig', (new TwigCommand())->getName());
    }

    public function testTwigCommandSuccessWhenNoLegacyClasses(): void
    {
        $dir    = $this->makeDir(['Ext.php' => 'use Twig\\Extension\\AbstractExtension;']);
        $tester = $this->testerFor(new TwigCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(0, $code);
        $this->removeDir($dir);
    }

    public function testTwigCommandDetectsTwigExtension(): void
    {
        $dir    = $this->makeDir(['Ext.php' => 'class MyExt extends \\Twig_Extension {}']);
        $tester = $this->testerFor(new TwigCommand());
        $code   = $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame(1, $code);
        $this->removeDir($dir);
    }

    public function testTwigCommandOutputShowsMigrationMap(): void
    {
        $dir    = $this->makeDir(['Ext.php' => 'class MyExt extends \\Twig_Extension {}']);
        $tester = $this->testerFor(new TwigCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $out = $tester->getDisplay();
        $this->assertStringContainsString('AbstractExtension', $out);
        $this->assertStringContainsString('TwigFilter', $out);
        $this->removeDir($dir);
    }

    /**
     * SAFETY: twig command must never write any file.
     */
    public function testTwigCommandNeverWritesFiles(): void
    {
        $dir    = $this->makeDir(['Ext.php' => 'class MyExt extends \\Twig_Extension {}']);
        $before = $this->dirChecksums($dir);
        $tester = $this->testerFor(new TwigCommand());
        $tester->execute(['--dir' => $dir], ['decorated' => false]);
        $this->assertSame($before, $this->dirChecksums($dir), 'prime:migrate:twig must not write any file');
        $this->removeDir($dir);
    }
}
