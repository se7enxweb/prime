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
 * prime:migrate:nullable
 *
 * Scans PHP source files for implicit nullable parameter declarations
 * (the PHP 8.4+ deprecated pattern `TypeHint $param = null` without a
 * leading `?`) and optionally rewrites them to the correct explicit form.
 *
 * Pass-model / safety contract
 * ────────────────────────────
 * The command operates in three distinct modes:
 *
 *   (default — no flags)    Read-only scan. Lists every affected file and line.
 *                           Nothing is written.
 *
 *   --fix --dry-run         Preview mode. Computes and displays the lines that
 *                           WOULD be changed, but does NOT write any file.
 *
 *   --fix                   Write mode. Applies the fix to every detected file.
 *                           The original content is checked to have changed before
 *                           writing; an identical file is never touched.
 *                           NO backup file is created — the caller must commit or
 *                           stash before running with --fix (see --help).
 *
 * Usage:
 *   php bin/console prime:migrate:nullable --dir=src/
 *   php bin/console prime:migrate:nullable --dir=src/ --fix --dry-run
 *   php bin/console prime:migrate:nullable --dir=src/ --fix
 *
 * @author 7x <info@se7enx.com>
 */
class NullableCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:nullable')
            ->setDescription('Scan (and optionally fix) implicit nullable parameter types for PHP 8.4+')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:nullable</info> command finds every PHP method or function parameter
that has a non-nullable type hint with a <comment>= null</comment> default — a pattern deprecated
in PHP 8.4 and a hard error in future PHP versions.

<comment>Read-only scan (default — nothing is written):</comment>
  <info>php bin/console prime:migrate:nullable --dir=src/</info>

<comment>Preview the fix without writing:</comment>
  <info>php bin/console prime:migrate:nullable --dir=src/ --fix --dry-run</info>

<comment>Apply the fix (writes files):</comment>
  <info>php bin/console prime:migrate:nullable --dir=src/ --fix</info>

<comment>IMPORTANT:</comment> Commit or stash your current changes before running with --fix.
Review the result with <comment>git diff src/</comment> before committing.

The fixer handles simple, unambiguous cases. Review manually any parameters
involving union types (<comment>int|string $p = null</comment>), intersection types, or <comment>mixed</comment>
— those require a conscious design decision, not a mechanical <comment>?</comment> prefix.
HELP)
            ->addDirOption()
            ->addOption(
                'fix',
                null,
                InputOption::VALUE_NONE,
                'Apply the fix to detected files (combine with --dry-run to preview first)'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would change without writing any file (requires --fix)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir    = $this->resolveDir($input);
        $doFix  = $input->getOption('fix');
        $dryRun = $input->getOption('dry-run');

        // --dry-run without --fix is a no-op that could confuse the user.
        if ($dryRun && !$doFix) {
            $output->writeln('<comment>Note: --dry-run has no effect without --fix. Running in read-only scan mode.</comment>');
            $dryRun = false;
        }

        $this->writeHeading($output, 'Implicit Nullable Type Scanner');
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

        // ── Scan ─────────────────────────────────────────────────────────────

        $results    = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanNullable'], $dir);
        $totalFiles = $this->countFiles($results);
        $totalIssues = $this->countIssues($results);

        if ($totalIssues === 0) {
            $output->writeln('<info>No implicit nullable declarations found.</info>');
            $output->writeln('');
            return 0;
        }

        // ── Report ────────────────────────────────────────────────────────────

        foreach ($results as $relPath => $issues) {
            $this->writeFile($output, $relPath);
            foreach ($issues as $issue) {
                $this->writeIssue($output, $issue['line'], $issue['snippet']);
            }
            $output->writeln('');
        }

        $output->writeln(sprintf(
            'Found <comment>%d</comment> implicit nullable %s in <comment>%d</comment> %s.',
            $totalIssues,
            $this->issueWord($totalIssues),
            $totalFiles,
            $totalFiles === 1 ? 'file' : 'files'
        ));

        // ── Fix pass ──────────────────────────────────────────────────────────

        if (!$doFix) {
            $output->writeln('');
            $output->writeln('To fix automatically:');
            $output->writeln(sprintf(
                '  <info>php bin/console prime:migrate:nullable --dir=%s --fix --dry-run</info>   (preview first)',
                basename($dir)
            ));
            $output->writeln(sprintf(
                '  <info>php bin/console prime:migrate:nullable --dir=%s --fix</info>              (apply)',
                basename($dir)
            ));
            $output->writeln('');
            $output->writeln("Always review the diff with <comment>git diff {$dir}</comment> after applying.");
            $output->writeln('');
            return 1;
        }

        // Apply (or dry-run) the fix
        $fixedFiles = 0;
        $fixedCount = 0;

        $output->writeln('');

        foreach ($results as $relPath => $issues) {
            $absPath = $dir . DIRECTORY_SEPARATOR . $relPath;
            $content = @file_get_contents($absPath);
            if ($content === false) {
                $output->writeln(sprintf('<error>Cannot read: %s</error>', $relPath));
                continue;
            }

            ['fixed' => $fixed, 'count' => $changes] = self::applyNullableFix($content);

            if ($changes === 0 || $fixed === $content) {
                continue; // Scanner found something but fixer made no change — skip.
            }

            if ($dryRun) {
                $output->writeln(sprintf(
                    ' <info>[DRY RUN]</info> <comment>%s</comment> — %d replacement%s would be made',
                    $relPath,
                    $changes,
                    $changes === 1 ? '' : 's'
                ));
                $this->printDiff($output, $content, $fixed, $relPath);
            } else {
                $written = file_put_contents($absPath, $fixed);
                if ($written === false) {
                    $output->writeln(sprintf('<error>Cannot write: %s</error>', $relPath));
                    continue;
                }
                $output->writeln(sprintf(
                    ' <info>[FIXED]</info> <comment>%s</comment> — %d replacement%s',
                    $relPath,
                    $changes,
                    $changes === 1 ? '' : 's'
                ));
            }

            ++$fixedFiles;
            $fixedCount += $changes;
        }

        $output->writeln('');

        if ($dryRun) {
            $output->writeln(sprintf(
                '<info>Dry run complete. %d replacement%s in %d file%s would be made.</info>',
                $fixedCount,
                $fixedCount === 1 ? '' : 's',
                $fixedFiles,
                $fixedFiles === 1 ? '' : 's'
            ));
            $output->writeln('Remove <comment>--dry-run</comment> to apply the changes.');
        } else {
            $output->writeln(sprintf(
                '<info>Done. %d replacement%s made across %d file%s.</info>',
                $fixedCount,
                $fixedCount === 1 ? '' : 's',
                $fixedFiles,
                $fixedFiles === 1 ? '' : 's'
            ));
            $output->writeln('Review with <comment>git diff ' . $dir . '</comment> before committing.');
        }

        $output->writeln('');

        return 0;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Prints a compact unified-style diff of the changed lines only.
     *
     * This is intentionally simple (not a full unified diff) — it shows the
     * before and after for each line that was touched, which is sufficient for
     * a preview when the fix is mechanical (? prefix insertion).
     */
    private function printDiff(OutputInterface $output, string $before, string $after, string $label): void
    {
        $beforeLines = explode("\n", $before);
        $afterLines  = explode("\n", $after);
        $total       = max(count($beforeLines), count($afterLines));

        for ($i = 0; $i < $total; ++$i) {
            $b = $beforeLines[$i] ?? '';
            $a = $afterLines[$i]  ?? '';
            if ($b !== $a) {
                $output->writeln(sprintf('    <error>- %s</error>', rtrim($b)));
                $output->writeln(sprintf('    <info>+ %s</info>', rtrim($a)));
            }
        }
    }
}
