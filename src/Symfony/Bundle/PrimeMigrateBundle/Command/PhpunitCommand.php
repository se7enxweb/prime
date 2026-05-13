<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeMigrateBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * prime:migrate:phpunit
 *
 * Scans PHP files for PHP 8.x compatibility issues common in test code and
 * optionally fixes them automatically.
 *
 * Detected (and auto-fixed with --fix):
 *   1. Non-static PHPUnit data-provider methods (@dataProvider / #[DataProvider()])
 *   2. Missing return type declarations on known interface method overrides
 *      (Countable, IteratorAggregate, ArrayAccess, JsonSerializable, ...)
 *   3. Deprecated Serializable interface usage (adds __serialize()/__unserialize() bridge)
 *
 * Detected (scan only - manual fix required):
 *   4. Optional parameters declared before required parameters
 *
 * Usage:
 *   php bin/console prime:migrate:phpunit --dir=src/
 *   php bin/console prime:migrate:phpunit --dir=src/ --fix --dry-run
 *   php bin/console prime:migrate:phpunit --dir=src/ --fix
 *
 * @author 7x <info@se7enx.com>
 */
class PhpunitCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:phpunit')
            ->setDescription('Scan (and optionally fix) PHP 8.x compatibility issues in PHP files')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:phpunit</info> command scans PHP files for PHP 8.x compatibility
issues and optionally fixes them automatically.

<comment>Auto-fixable issues (--fix):</comment>

  <info>1. Non-static data-provider methods</info>
    PHPUnit 11 requires every method referenced by <comment>@dataProvider</comment> or
    <comment>#[DataProvider()]</comment> to be declared <info>public static</info>.

  <info>2. Missing return types on interface method overrides</info>
    PHP 8.1+ requires return types to match the declaring interface.
    Affected interfaces: Countable, IteratorAggregate, ArrayAccess,
    JsonSerializable, Stringable, SessionHandlerInterface, PDO, Iterator.

  <info>3. Deprecated Serializable interface</info>
    PHP 8.1+ deprecates implementing <comment>\Serializable</comment> alone.
    Adds <info>__serialize()</info>/<info>__unserialize()</info> bridge methods that delegate to
    the existing <comment>serialize()</comment>/<comment>unserialize()</comment> methods.

<comment>Scan-only issues (manual fix required):</comment>

  <info>4. Optional parameters declared before required parameters</info>
    PHP 8.0+ deprecates <comment>function foo($opt = null, $required)</comment>.
    Fix by reordering parameters or removing the default value.

<comment>Read-only scan:</comment>
  <info>php bin/console prime:migrate:phpunit --dir=src/</info>

<comment>Preview the fix without writing:</comment>
  <info>php bin/console prime:migrate:phpunit --dir=src/ --fix --dry-run</info>

<comment>Apply the fix:</comment>
  <info>php bin/console prime:migrate:phpunit --dir=src/ --fix</info>

<comment>IMPORTANT:</comment> Commit or stash your current changes before running with --fix.
Review the result with <comment>git diff src/</comment> before committing.
HELP)
            ->addDirOption()
            ->addOption('fix',     null, InputOption::VALUE_NONE, 'Apply fixes to detected files (combine with --dry-run to preview first)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would change without writing any file (requires --fix)')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir    = $this->resolveDir($input);
        $doFix  = $input->getOption('fix');
        $dryRun = $input->getOption('dry-run');

        if ($dryRun && !$doFix) {
            $output->writeln('<comment>Note: --dry-run has no effect without --fix. Running in read-only scan mode.</comment>');
            $dryRun = false;
        }

        $this->writeHeading($output, 'PHP 8.x Compatibility Scanner');
        $output->writeln(sprintf('Scanning: <comment>%s</comment>', $dir));

        if ($doFix && !$dryRun) {
            $output->writeln('');
            $output->writeln('<comment>Mode: FIX (files will be written)</comment>');
            $output->writeln('<comment>Ensure your changes are committed before proceeding.</comment>');
        } elseif ($dryRun) {
            $output->writeln('');
            $output->writeln('<info>Mode: DRY RUN (no files will be written)</info>');
        }

        $output->writeln('');

        // ── Run all scanners ──────────────────────────────────────────────────
        $phpunitResults      = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanPhpunit'],              $dir);
        $returnTypeResults   = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanReturnTypeCompat'],     $dir);
        $serializableResults = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanSerializable'],         $dir);
        $optParamResults     = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanOptionalBeforeRequired'], $dir);

        $phpunitCount      = $this->countIssues($phpunitResults);
        $returnTypeCount   = $this->countIssues($returnTypeResults);
        $serializableCount = $this->countIssues($serializableResults);
        $optParamCount     = $this->countIssues($optParamResults);
        $totalIssues       = $phpunitCount + $returnTypeCount + $serializableCount + $optParamCount;

        if ($totalIssues === 0) {
            $output->writeln('<info>No PHP 8.x compatibility issues found.</info>');
            $output->writeln('');
            return 0;
        }

        // ── Non-static data providers ─────────────────────────────────────────
        if ($phpunitCount > 0) {
            $output->writeln(sprintf(
                '<comment>▸ [1/4] Non-static data-provider methods</comment> (%d %s)',
                $phpunitCount, $this->issueWord($phpunitCount)
            ));
            $output->writeln('');
            foreach ($phpunitResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                    $output->writeln(sprintf(
                        '           <comment>public function %s(</comment>  →  <info>public static function %s(</info>',
                        $issue['method'], $issue['method']
                    ));
                }
                $output->writeln('');
            }
        }

        // ── Missing return types ──────────────────────────────────────────────
        if ($returnTypeCount > 0) {
            $output->writeln(sprintf(
                '<comment>▸ [2/4] Missing return types on interface method overrides</comment> (%d %s)',
                $returnTypeCount, $this->issueWord($returnTypeCount)
            ));
            $output->writeln('');
            foreach ($returnTypeResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                    $output->writeln(sprintf(
                        '           Add return type: <info>: %s</info>',
                        $issue['expectedType']
                    ));
                }
                $output->writeln('');
            }
        }

        // ── Serializable interface ────────────────────────────────────────────
        if ($serializableCount > 0) {
            $output->writeln(sprintf(
                '<comment>▸ [3/4] Deprecated Serializable interface</comment> (%d %s)',
                $serializableCount, $this->issueWord($serializableCount)
            ));
            $output->writeln('');
            foreach ($serializableResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                    $output->writeln(
                        '           Add <info>__serialize(): array</info> + <info>__unserialize(array $data): void</info>'
                    );
                }
                $output->writeln('');
            }
        }

        // ── Optional before required (scan only) ──────────────────────────────
        if ($optParamCount > 0) {
            $output->writeln(sprintf(
                '<comment>▸ [4/4] Optional parameters before required (manual fix required)</comment> (%d %s)',
                $optParamCount, $this->issueWord($optParamCount)
            ));
            $output->writeln('');
            foreach ($optParamResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                    $output->writeln(sprintf(
                        '           Optional param <comment>%s</comment> precedes a required param — reorder or remove the default.',
                        $issue['param']
                    ));
                }
                $output->writeln('');
            }
        }

        // ── Summary ───────────────────────────────────────────────────────────
        $fixable = $phpunitCount + $returnTypeCount + $serializableCount;

        $output->writeln(sprintf(
            'Found <comment>%d</comment> %s total (%d auto-fixable, %d manual).',
            $totalIssues, $this->issueWord($totalIssues), $fixable, $optParamCount
        ));

        if (!$doFix) {
            $dirArg = basename($dir);
            $output->writeln('');
            if ($fixable > 0) {
                $output->writeln('To fix the auto-fixable issues:');
                $output->writeln(sprintf(
                    '  <info>php bin/console prime:migrate:phpunit --dir=%s --fix --dry-run</info>   (preview first)', $dirArg
                ));
                $output->writeln(sprintf(
                    '  <info>php bin/console prime:migrate:phpunit --dir=%s --fix</info>              (apply)', $dirArg
                ));
            } else {
                $output->writeln('All detected issues require manual fixes.');
            }
            $output->writeln('');
            return 1;
        }

        // ── Fix pass ──────────────────────────────────────────────────────────

        $output->writeln('');

        // Merge all fixable results by file path
        $allFixableFiles = array_unique(array_merge(
            array_keys($phpunitResults),
            array_keys($returnTypeResults),
            array_keys($serializableResults)
        ));

        $fixedFiles = 0;
        $fixedCount = 0;

        foreach ($allFixableFiles as $relPath) {
            $absPath = $dir . DIRECTORY_SEPARATOR . $relPath;
            $content = @file_get_contents($absPath);
            if ($content === false) {
                $output->writeln(sprintf('<error>Cannot read: %s</error>', $relPath));
                continue;
            }

            $changes      = 0;
            $fixedContent = $content;

            // Apply each fixer in sequence on the (already modified) content
            if (isset($phpunitResults[$relPath])) {
                ['fixed' => $fixedContent, 'count' => $n] = self::applyPhpunitFix($fixedContent);
                $changes += $n;
            }
            if (isset($returnTypeResults[$relPath])) {
                ['fixed' => $fixedContent, 'count' => $n] = self::applyReturnTypeCompatFix($fixedContent);
                $changes += $n;
            }
            if (isset($serializableResults[$relPath])) {
                ['fixed' => $fixedContent, 'count' => $n] = self::applySerializableFix($fixedContent);
                $changes += $n;
            }

            if ($changes === 0 || $fixedContent === $content) {
                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf(
                    ' <info>[DRY RUN]</info> <comment>%s</comment> — %d replacement%s',
                    $relPath, $changes, $changes === 1 ? '' : 's'
                ));
            } else {
                if (file_put_contents($absPath, $fixedContent) === false) {
                    $output->writeln(sprintf('<error>Cannot write: %s</error>', $relPath));
                    continue;
                }
                $output->writeln(sprintf(
                    ' <info>[FIXED]</info> <comment>%s</comment> — %d replacement%s',
                    $relPath, $changes, $changes === 1 ? '' : 's'
                ));
            }

            ++$fixedFiles;
            $fixedCount += $changes;
        }

        $output->writeln('');

        if ($dryRun) {
            $output->writeln(sprintf(
                '<info>Dry run complete. %d replacement%s in %d file%s would be applied.</info>',
                $fixedCount, $fixedCount === 1 ? '' : 's',
                $fixedFiles, $fixedFiles === 1 ? '' : 's'
            ));
        } else {
            $output->writeln(sprintf(
                '<info>Done. Applied %d replacement%s across %d file%s.</info>',
                $fixedCount, $fixedCount === 1 ? '' : 's',
                $fixedFiles, $fixedFiles === 1 ? '' : 's'
            ));
            $output->writeln('');
            $output->writeln('Review the changes:');
            $output->writeln('  <info>git diff ' . basename($dir) . '/</info>');
        }

        if ($optParamCount > 0) {
            $output->writeln('');
            $output->writeln(sprintf(
                '<comment>Note: %d optional-before-required %s still require manual fixes.</comment>',
                $optParamCount, $this->issueWord($optParamCount)
            ));
        }

        $output->writeln('');

        return $optParamCount > 0 ? 1 : 0;
    }
}
