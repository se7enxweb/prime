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
 * prime:migrate:yaml
 *
 * Scans YAML files for !php/object: and !!php/object: tags. In 7x Prime,
 * YAML deserialisation of PHP objects is blocked by passing
 * ['allowed_classes' => false] to unserialize(). Any YAML that relied on
 * this feature will no longer produce PHP objects at parse time.
 *
 * Usage:
 *   php bin/console prime:migrate:yaml
 *   php bin/console prime:migrate:yaml --dir=app/config
 *   php bin/console prime:migrate:yaml --dir=app/config --fix --dry-run
 *   php bin/console prime:migrate:yaml --dir=app/config --fix
 *
 * @author 7x <info@se7enx.com>
 */
class YamlCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:yaml')
            ->setDescription('Scan (and optionally fix) YAML !php/object: PHP object deserialisation tags')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:yaml</info> command scans YAML files for PHP object deserialisation
tags (<comment>!php/object:</comment> and <comment>!!php/object:</comment>).

In 7x Prime, <comment>Yaml::parse()</comment> passes <comment>['allowed_classes' => false]</comment> to
<comment>unserialize()</comment> even when <comment>$objectSupport = true</comment> is set. YAML that previously
produced PHP objects will now return <comment>false</comment> (or throw, depending on the caller).

The <comment>--fix</comment> option strips the <comment>!php/object:</comment> tag, leaving the serialised value
as a plain YAML string. Any consuming code that relied on the value being a
PHP object must be updated to call <comment>unserialize()</comment> explicitly.

<comment>Read-only scan (default — nothing is written):</comment>
  <info>php bin/console prime:migrate:yaml --dir=app/config</info>

<comment>Preview the fix without writing:</comment>
  <info>php bin/console prime:migrate:yaml --dir=app/config --fix --dry-run</info>

<comment>Apply the fix:</comment>
  <info>php bin/console prime:migrate:yaml --dir=app/config --fix</info>

<comment>IMPORTANT:</comment> Commit or stash your current changes before running with --fix.
After the fix, update any code that reads these YAML values — they will now
be plain strings and must be passed to <comment>unserialize()</comment> if object reconstruction
is still required. See <comment>MIGRATION.md — Step 7</comment>.
HELP)
            ->addDirOption()
            ->addOption('fix',     null, InputOption::VALUE_NONE, 'Strip !php/object: tags (leaves value as plain string — consuming code must be updated)')
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

        $this->writeHeading($output, 'YAML PHP Object Deserialisation Scanner');
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

        $results     = $this->scanFiles($this->yamlFiles($dir), [self::class, 'scanYaml'], $dir);
        $totalFiles  = $this->countFiles($results);
        $totalIssues = $this->countIssues($results);

        if ($totalIssues === 0) {
            $output->writeln('<info>No YAML PHP object tags found. Safe to proceed.</info>');
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
            'Found <comment>%d</comment> YAML file%s with PHP object tags.',
            $totalFiles,
            $totalFiles === 1 ? '' : 's'
        ));
        $output->writeln('');

        if (!$doFix) {
            $output->writeln('These values will <error>no longer</error> deserialise to PHP objects in 7x Prime.');
            $output->writeln('');
            $output->writeln('The <comment>--fix</comment> option strips the <comment>!php/object:</comment> tag,');
            $output->writeln('leaving each value as a plain YAML string. Consuming code must call');
            $output->writeln('<comment>unserialize()</comment> explicitly if object reconstruction is still required.');
            $output->writeln('');
            $dirArg = basename($dir);
            $output->writeln('To fix automatically:');
            $output->writeln(sprintf('  <info>php bin/console prime:migrate:yaml --dir=%s --fix --dry-run</info>   (preview first)', $dirArg));
            $output->writeln(sprintf('  <info>php bin/console prime:migrate:yaml --dir=%s --fix</info>              (apply)', $dirArg));
            $output->writeln('');
            $output->writeln('Reference: <comment>MIGRATION.md — Step 7 (YAML Object Deserialisation)</comment>');
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

            ['fixed' => $fixed, 'count' => $changes] = self::applyYamlFix($content);

            if ($changes === 0 || $fixed === $content) {
                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf(
                    ' <info>[DRY RUN]</info> <comment>%s</comment> — %d tag%s would be stripped',
                    $relPath, $changes, $changes === 1 ? '' : 's'
                ));
                foreach ($issues as $issue) {
                    $output->writeln(sprintf(
                        '    Line %d:  <comment>%s</comment>  → tag stripped, value becomes plain string',
                        $issue['line'],
                        trim($issue['snippet'])
                    ));
                }
            } else {
                if (file_put_contents($absPath, $fixed) === false) {
                    $output->writeln(sprintf('<error>Cannot write: %s</error>', $relPath));
                    continue;
                }
                $output->writeln(sprintf(
                    ' <info>[FIXED]</info> <comment>%s</comment> — %d tag%s stripped',
                    $relPath, $changes, $changes === 1 ? '' : 's'
                ));
            }

            ++$fixedFiles;
            $fixedCount += $changes;
        }

        $output->writeln('');

        if ($dryRun) {
            $output->writeln(sprintf(
                '<info>Dry run complete. %d tag%s in %d file%s would be stripped.</info>',
                $fixedCount, $fixedCount === 1 ? '' : 's',
                $fixedFiles, $fixedFiles === 1 ? '' : 's'
            ));
        } else {
            $output->writeln(sprintf(
                '<info>Done. %d tag%s stripped across %d file%s.</info>',
                $fixedCount, $fixedCount === 1 ? '' : 's',
                $fixedFiles, $fixedFiles === 1 ? '' : 's'
            ));
            $output->writeln(sprintf('Review with <comment>git diff %s</comment> before committing.', $dir));
            $output->writeln('<comment>Remember: update consuming code to call unserialize() on the now-string values.</comment>');
        }

        $output->writeln('');

        return 0;
    }
}
