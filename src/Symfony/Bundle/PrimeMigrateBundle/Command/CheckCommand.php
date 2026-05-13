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
 * prime:migrate:check
 *
 * Runs all available compatibility scans in sequence and prints a consolidated
 * summary. This is the recommended first command to run after installing 7x Prime
 * or before starting a PHP 8.x migration.
 *
 * Usage:
 *   php bin/console prime:migrate:check
 *   php bin/console prime:migrate:check --dir=src/MyBundle
 *   php bin/console prime:migrate:check --dir=src/ --verbose
 *
 * SAFETY: This command is 100% read-only. It never writes, modifies, or deletes
 * any file. It only reads source files and reports what it finds.
 *
 * @author 7x <info@se7enx.com>
 */
class CheckCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:check')
            ->setDescription('Comprehensive PHP 8.5 compatibility scan — runs all checks (read-only)')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:check</info> command runs all available migration compatibility
scans over your source directory and prints a consolidated summary.

It is the recommended starting point for any 7x Prime migration. Run it
before touching any code to understand the full scope of work.

  <info>php bin/console prime:migrate:check</info>
  <info>php bin/console prime:migrate:check --dir=src/MyBundle</info>
  <info>php bin/console prime:migrate:check --verbose</info>

<comment>This command is 100% read-only — it never modifies any file.</comment>

After reviewing the summary, use the individual sub-commands for detail:
  <info>php bin/console prime:migrate:nullable</info>   — implicit nullable types
  <info>php bin/console prime:migrate:forms</info>       — string form type aliases
  <info>php bin/console prime:migrate:constraints</info> — reserved keyword constraints
  <info>php bin/console prime:migrate:yaml</info>        — YAML !php/object: tags
  <info>php bin/console prime:migrate:twig</info>        — Twig legacy class names
  <info>php bin/console prime:migrate:report</info>      — full report (text/HTML/JSON)
HELP)
            ->addDirOption()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir     = $this->resolveDir($input);
        $verbose = $output->isVerbose();

        $output->writeln('');
        $output->writeln('<info>7x Prime Migration Check — PHP 8.5 Compatibility</info>');
        $output->writeln(str_repeat('=', 49));
        $output->writeln(sprintf('Directory: <comment>%s</comment>', $dir));
        $output->writeln('');

        // ── Run all scans ─────────────────────────────────────────────────────

        // 1. Implicit nullable types (PHP files)
        $nullableResults = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanNullable'], $dir);
        $nullableCount   = $this->countIssues($nullableResults);
        $this->writeScanRow($output, 'Implicit nullable types', $nullableCount);

        if ($verbose && $nullableCount > 0) {
            foreach ($nullableResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                }
            }
            $output->writeln('');
        }

        // 2. String form type aliases (PHP files)
        $formsResults = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanForms'], $dir);
        $formsCount   = $this->countIssues($formsResults);
        $this->writeScanRow($output, 'String form type names', $formsCount);

        if ($verbose && $formsCount > 0) {
            foreach ($formsResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssueWithSuggestion($output, $issue['line'], $issue['snippet'], $issue['fqcn']);
                }
            }
            $output->writeln('');
        }

        // 3. Reserved keyword validator constraint names (PHP files)
        $constraintResults = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanConstraints'], $dir);
        $constraintCount   = $this->countIssues($constraintResults);
        $this->writeScanRow($output, 'Reserved constraint names', $constraintCount);

        if ($verbose && $constraintCount > 0) {
            foreach ($constraintResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                }
            }
            $output->writeln('');
        }

        // 4. YAML !php/object: tags (YAML files)
        $yamlResults = $this->scanFiles($this->yamlFiles($dir), [self::class, 'scanYaml'], $dir);
        $yamlCount   = $this->countIssues($yamlResults);
        $this->writeScanRow($output, 'YAML !php/object usage', $yamlCount);

        if ($verbose && $yamlCount > 0) {
            foreach ($yamlResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                }
            }
            $output->writeln('');
        }

        // 5. Twig_* legacy class references (PHP files)
        $twigResults = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanTwig'], $dir);
        $twigCount   = $this->countIssues($twigResults);
        $this->writeScanRow($output, 'Twig_* class references', $twigCount);

        if ($verbose && $twigCount > 0) {
            foreach ($twigResults as $relPath => $issues) {
                $this->writeFile($output, $relPath);
                foreach ($issues as $issue) {
                    $suggestion = $issue['suggestion'] ? ' → ' . $issue['suggestion'] : '';
                    $this->writeIssue($output, $issue['line'], $issue['snippet'] . $suggestion);
                }
            }
            $output->writeln('');
        }

        // ── Summary ───────────────────────────────────────────────────────────

        $totalIssues = $nullableCount + $formsCount + $constraintCount + $yamlCount + $twigCount;

        $allFiles = array_unique(array_merge(
            array_keys($nullableResults),
            array_keys($formsResults),
            array_keys($constraintResults),
            array_keys($yamlResults),
            array_keys($twigResults)
        ));

        $output->writeln('');

        if ($totalIssues === 0) {
            $output->writeln('<info>Total: no issues found. Migration looks complete!</info>');
        } else {
            $output->writeln(sprintf(
                '<comment>Total: %d %s across %d %s.</comment>',
                $totalIssues,
                $this->issueWord($totalIssues),
                count($allFiles),
                count($allFiles) === 1 ? 'file' : 'files'
            ));
            $output->writeln('');
            $output->writeln('Run <info>php bin/console prime:migrate:report</info> for full detail.');

            if ($nullableCount > 0) {
                $output->writeln('Run <info>php bin/console prime:migrate:nullable --fix --dir=' . basename($dir) . '/</info> to auto-fix nullable types (preview with --dry-run first).');
            }
        }

        $output->writeln('');

        return $totalIssues > 0 ? 1 : 0;
    }
}
