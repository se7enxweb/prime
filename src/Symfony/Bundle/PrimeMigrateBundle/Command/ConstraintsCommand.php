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
 * prime:migrate:constraints
 *
 * Scans PHP source files for usages of the PHP-reserved Validator constraint
 * names: True, False, and Null under the
 * Symfony\Component\Validator\Constraints namespace.
 *
 * These names are PHP 8 keywords. 7x Prime provides class_alias() shims so
 * the old names continue to work at runtime, but PHP code that references
 * them should be updated to IsTrue, IsFalse, and IsNull.
 *
 * Usage:
 *   php bin/console prime:migrate:constraints --dir=src/
 *   php bin/console prime:migrate:constraints --dir=src/ --fix --dry-run
 *   php bin/console prime:migrate:constraints --dir=src/ --fix
 *
 * @author 7x <info@se7enx.com>
 */
class ConstraintsCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:constraints')
            ->setDescription('Scan (and optionally fix) reserved-keyword Validator constraint names')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:constraints</info> command scans for usages of the PHP 8 reserved
keyword constraint class names and optionally rewrites them automatically:

  <comment>Constraints\True</comment>  →  <info>Constraints\IsTrue</info>
  <comment>Constraints\False</comment> →  <info>Constraints\IsFalse</info>
  <comment>Constraints\Null</comment>  →  <info>Constraints\IsNull</info>

7x Prime provides <comment>class_alias()</comment> shims so the old names still work at runtime,
but PHP code that references them may trigger deprecation notices or analysis
tool warnings. YAML / XML constraint configuration does NOT need to be updated.

<comment>Read-only scan (default — nothing is written):</comment>
  <info>php bin/console prime:migrate:constraints --dir=src/</info>

<comment>Preview the fix without writing:</comment>
  <info>php bin/console prime:migrate:constraints --dir=src/ --fix --dry-run</info>

<comment>Apply the fix:</comment>
  <info>php bin/console prime:migrate:constraints --dir=src/ --fix</info>

<comment>IMPORTANT:</comment> Commit or stash your current changes before running with --fix.
Review the result with <comment>git diff src/</comment> before committing.
HELP)
            ->addDirOption()
            ->addOption('fix',     null, InputOption::VALUE_NONE, 'Apply the fix to detected files (combine with --dry-run to preview first)')
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

        $this->writeHeading($output, 'Validator Reserved Keyword Constraint Scanner');
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

        $results     = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanConstraints'], $dir);
        $totalFiles  = $this->countFiles($results);
        $totalIssues = $this->countIssues($results);

        if ($totalIssues === 0) {
            $output->writeln('<info>No reserved-keyword constraint names found.</info>');
            $output->writeln('');
            return 0;
        }

        foreach ($results as $relPath => $issues) {
            $this->writeFile($output, $relPath);
            foreach ($issues as $issue) {
                $this->writeIssue($output, $issue['line'], $issue['snippet']);
            }
            $output->writeln('');
        }

        $output->writeln(sprintf(
            'Found <comment>%d</comment> %s in <comment>%d</comment> %s with reserved-keyword constraint references.',
            $totalIssues,
            $this->issueWord($totalIssues),
            $totalFiles,
            $totalFiles === 1 ? 'file' : 'files'
        ));

        if (!$doFix) {
            $output->writeln('');
            $output->writeln('Migrate to:');
            $output->writeln('  <comment>Constraints\\True</comment>  →  <info>Constraints\\IsTrue</info>');
            $output->writeln('  <comment>Constraints\\False</comment> →  <info>Constraints\\IsFalse</info>');
            $output->writeln('  <comment>Constraints\\Null</comment>  →  <info>Constraints\\IsNull</info>');
            $output->writeln('');
            $output->writeln('YAML / XML configuration files do not need to be updated.');
            $output->writeln('The old names continue to work via <comment>class_alias()</comment> in 7x Prime.');
            $dirArg = basename($dir);
            $output->writeln('');
            $output->writeln('To fix automatically:');
            $output->writeln(sprintf('  <info>php bin/console prime:migrate:constraints --dir=%s --fix --dry-run</info>   (preview first)', $dirArg));
            $output->writeln(sprintf('  <info>php bin/console prime:migrate:constraints --dir=%s --fix</info>              (apply)', $dirArg));
            $output->writeln('');
            return 1;
        }

        // ── Fix pass ──────────────────────────────────────────────────────────

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

            ['fixed' => $fixed, 'count' => $changes] = self::applyConstraintsFix($content);

            if ($changes === 0 || $fixed === $content) {
                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf(
                    ' <info>[DRY RUN]</info> <comment>%s</comment> — %d replacement%s',
                    $relPath, $changes, $changes === 1 ? '' : 's'
                ));
                foreach ($issues as $issue) {
                    $output->writeln(sprintf(
                        "    Line %d:  <comment>%s</comment>  <info>→ %s</info>",
                        $issue['line'],
                        'Constraints\\' . $issue['old'],
                        'Constraints\\Is' . $issue['old']
                    ));
                }
            } else {
                if (file_put_contents($absPath, $fixed) === false) {
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
                '<info>Dry run complete. %d replacement%s in %d file%s would be made.</info>',
                $fixedCount, $fixedCount === 1 ? '' : 's',
                $fixedFiles, $fixedFiles === 1 ? '' : 's'
            ));
        } else {
            $output->writeln(sprintf(
                '<info>Done. %d replacement%s made across %d file%s.</info>',
                $fixedCount, $fixedCount === 1 ? '' : 's',
                $fixedFiles, $fixedFiles === 1 ? '' : 's'
            ));
            $output->writeln(sprintf('Review with <comment>git diff %s</comment> before committing.', $dir));
        }

        $output->writeln('');

        return 0;
    }
}
