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
 * prime:migrate:twig
 *
 * Scans PHP source files for references to the Twig 1.x Twig_* class naming
 * convention. The se7enxweb/twig package provides compatibility shims for these
 * names, but migrating to the Twig\… PSR-4 namespace is recommended for
 * forward compatibility with Twig 3.x and beyond.
 *
 * Usage:
 *   php bin/console prime:migrate:twig --dir=src/
 *   php bin/console prime:migrate:twig --dir=src/ --fix --dry-run
 *   php bin/console prime:migrate:twig --dir=src/ --fix
 *
 * @author 7x <info@se7enx.com>
 */
class TwigCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:twig')
            ->setDescription('Scan (and optionally fix) Twig 1.x Twig_* legacy class references')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:twig</info> command scans PHP source files for references to the
Twig 1.x <comment>Twig_*</comment> class naming convention and optionally rewrites them.

Common migrations:
  <comment>\Twig_Extension</comment>         →  <info>Twig\Extension\AbstractExtension</info>
  <comment>\Twig_SimpleFilter</comment>      →  <info>Twig\TwigFilter</info>
  <comment>\Twig_SimpleFunction</comment>    →  <info>Twig\TwigFunction</info>
  <comment>\Twig_SimpleTest</comment>        →  <info>Twig\TwigTest</info>
  <comment>\Twig_Environment</comment>       →  <info>Twig\Environment</info>
  <comment>\Twig_Loader_Filesystem</comment> →  <info>Twig\Loader\FilesystemLoader</info>

Unknown <comment>Twig_*</comment> class names (not in the map above) are left unchanged and
must be migrated manually.

<comment>Read-only scan (default — nothing is written):</comment>
  <info>php bin/console prime:migrate:twig --dir=src/</info>

<comment>Preview the fix without writing:</comment>
  <info>php bin/console prime:migrate:twig --dir=src/ --fix --dry-run</info>

<comment>Apply the fix:</comment>
  <info>php bin/console prime:migrate:twig --dir=src/ --fix</info>

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

        $this->writeHeading($output, 'Twig Legacy Class Reference Scanner');
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

        $results     = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanTwig'], $dir);
        $totalFiles  = $this->countFiles($results);
        $totalIssues = $this->countIssues($results);

        if ($totalIssues === 0) {
            $output->writeln('<info>No Twig legacy class references found.</info>');
            $output->writeln('');
            return 0;
        }

        foreach ($results as $relPath => $issues) {
            $this->writeFile($output, $relPath);
            foreach ($issues as $issue) {
                if ($issue['suggestion']) {
                    $this->writeIssueWithSuggestion($output, $issue['line'], '\\' . $issue['class'], $issue['suggestion']);
                } else {
                    $this->writeIssue($output, $issue['line'], $issue['snippet']);
                }
            }
            $output->writeln('');
        }

        $output->writeln(sprintf(
            'Found <comment>%d</comment> %s in <comment>%d</comment> %s with Twig legacy class references.',
            $totalIssues,
            $this->issueWord($totalIssues),
            $totalFiles,
            $totalFiles === 1 ? 'file' : 'files'
        ));

        if (!$doFix) {
            $output->writeln('');
            $output->writeln('Recommended migration:');
            $output->writeln('  <comment>\Twig_Extension</comment>         →  <info>Twig\Extension\AbstractExtension</info>');
            $output->writeln('  <comment>\Twig_SimpleFilter</comment>      →  <info>Twig\TwigFilter</info>');
            $output->writeln('  <comment>\Twig_SimpleFunction</comment>    →  <info>Twig\TwigFunction</info>');
            $output->writeln('  <comment>\Twig_SimpleTest</comment>        →  <info>Twig\TwigTest</info>');
            $output->writeln('  <comment>\Twig_Environment</comment>       →  <info>Twig\Environment</info>');
            $output->writeln('  <comment>\Twig_Loader_Filesystem</comment> →  <info>Twig\Loader\FilesystemLoader</info>');
            $output->writeln('');
            $output->writeln('Legacy names continue to work via the <comment>se7enxweb/twig</comment> compatibility layer.');
            $dirArg = basename($dir);
            $output->writeln('');
            $output->writeln('To fix automatically (known Twig_* names only — review unknown references manually):');
            $output->writeln(sprintf('  <info>php bin/console prime:migrate:twig --dir=%s --fix --dry-run</info>   (preview first)', $dirArg));
            $output->writeln(sprintf('  <info>php bin/console prime:migrate:twig --dir=%s --fix</info>              (apply)', $dirArg));
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

            ['fixed' => $fixed, 'count' => $changes] = self::applyTwigFix($content);

            if ($changes === 0 || $fixed === $content) {
                // File may still have unknown Twig_* references — report those
                $unknown = array_filter($issues, static fn ($i) => $i['suggestion'] === null);
                if (!empty($unknown)) {
                    $output->writeln(sprintf(
                        ' <comment>[SKIPPED]</comment> %s — %d reference%s require manual migration (unknown Twig_* class)',
                        $relPath, count($unknown), count($unknown) === 1 ? '' : 's'
                    ));
                }
                continue;
            }

            if ($dryRun) {
                $output->writeln(sprintf(
                    ' <info>[DRY RUN]</info> <comment>%s</comment> — %d replacement%s',
                    $relPath, $changes, $changes === 1 ? '' : 's'
                ));
                foreach ($issues as $issue) {
                    if ($issue['suggestion']) {
                        $output->writeln(sprintf(
                            "    Line %d:  <comment>\\%s</comment>  <info>→ %s</info>",
                            $issue['line'], $issue['class'], $issue['suggestion']
                        ));
                    } else {
                        $output->writeln(sprintf(
                            "    Line %d:  <comment>%s</comment>  (no known replacement — manual fix required)",
                            $issue['line'], $issue['class']
                        ));
                    }
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
