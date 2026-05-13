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
 * prime:migrate:forms
 *
 * Scans PHP source files for string-based form type names — the Symfony 2.3–2.7
 * API deprecated in Symfony 2.8 and incompatible with Symfony 3.0+.
 *
 * 7x Prime retains the string aliases for backward compatibility but emits
 * deprecation notices. This command helps you locate them and optionally
 * rewrites them to the correct FQCN form.
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
 *   --fix                   Write mode. Replaces every string alias with the
 *                           corresponding FQCN short name AND injects the
 *                           required `use` statements into each file.
 *                           NO backup file is created — commit before running.
 *
 * Usage:
 *   php bin/console prime:migrate:forms --dir=src/
 *   php bin/console prime:migrate:forms --dir=src/ --fix --dry-run
 *   php bin/console prime:migrate:forms --dir=src/ --fix
 *
 * @author 7x <info@se7enx.com>
 */
class FormsCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:forms')
            ->setDescription('Scan (and optionally fix) string-based form type names for Symfony 3.0+ compatibility')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:forms</info> command scans your PHP source for the Symfony 2.3–2.7
string-based form type API:

  <comment>->add('name', TextType::class)</comment>     →  <info>->add('name', TextType::class)</info>
  <comment>->add('age',  IntegerType::class)</comment>  →  <info>->add('age',  IntegerType::class)</info>

These string aliases were deprecated in Symfony 2.8 and are not available in
Symfony 3.0+. 7x Prime keeps them for backward compatibility but logs deprecations.

<comment>Read-only scan (default — nothing is written):</comment>
  <info>php bin/console prime:migrate:forms --dir=src/</info>

<comment>Preview the fix without writing:</comment>
  <info>php bin/console prime:migrate:forms --dir=src/ --fix --dry-run</info>

<comment>Apply the fix (writes files + injects use statements):</comment>
  <info>php bin/console prime:migrate:forms --dir=src/ --fix</info>

<comment>IMPORTANT:</comment> Commit or stash your current changes before running with --fix.
Review the result with <comment>git diff src/</comment> before committing.

The fixer replaces each string alias with the FQCN short name (e.g. TextType::class)
and injects the corresponding <info>use</info> statements. Review files that use non-core form
types or custom form type classes — those require manual adjustment.
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

        $this->writeHeading($output, 'Form Type String Alias Scanner');
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

        $results     = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanForms'], $dir);
        $totalFiles  = $this->countFiles($results);
        $totalIssues = $this->countIssues($results);

        if ($totalIssues === 0) {
            $output->writeln('<info>No string form type aliases found. All form types use FQCN.</info>');
            $output->writeln('');
            return 0;
        }

        // ── Report ────────────────────────────────────────────────────────────

        foreach ($results as $relPath => $issues) {
            $this->writeFile($output, $relPath);
            foreach ($issues as $issue) {
                $this->writeIssueWithSuggestion(
                    $output,
                    $issue['line'],
                    sprintf("'%s'", $issue['alias']),
                    $issue['fqcn']
                );
            }
            $output->writeln('');
        }

        $output->writeln(sprintf(
            'Found <comment>%d</comment> string type %s in <comment>%d</comment> %s.',
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
                '  <info>php bin/console prime:migrate:forms --dir=%s --fix --dry-run</info>   (preview first)',
                basename($dir)
            ));
            $output->writeln(sprintf(
                '  <info>php bin/console prime:migrate:forms --dir=%s --fix</info>              (apply)',
                basename($dir)
            ));
            $output->writeln('');
            $output->writeln('Reference: <comment>MIGRATION.md — Step 9 (Form Type API)</comment> for the complete FQCN mapping table.');
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

            ['fixed' => $fixed, 'count' => $changes, 'injected' => $injectedUse] = self::applyFormsFix($content);

            if ($changes === 0 || $fixed === $content) {
                continue; // Scanner found something but fixer made no change — skip.
            }

            if ($dryRun) {
                $useCount = count($injectedUse);
                $output->writeln(sprintf(
                    ' <info>[DRY RUN]</info> <comment>%s</comment> — %d replacement%s, %d use statement%s would be added',
                    $relPath,
                    $changes,
                    $changes === 1 ? '' : 's',
                    $useCount,
                    $useCount === 1 ? '' : 's'
                ));
                foreach ($injectedUse as $fqn) {
                    $output->writeln('    <info>+ use ' . $fqn . ';</info>');
                }
                foreach ($issues as $issue) {
                    $output->writeln(sprintf(
                        "    Line %d:  '%s'  <info>→ %s</info>",
                        $issue['line'],
                        $issue['alias'],
                        $issue['fqcn']
                    ));
                }
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
}
