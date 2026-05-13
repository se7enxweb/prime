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
use Symfony\Component\Console\Output\OutputInterface;

/**
 * prime:migrate:twig
 *
 * Scans PHP source files for references to the Twig 1.x Twig_* class naming
 * convention. The se7enxweb/twig package provides compatibility shims for these
 * names, but migrating to the Twig\… PSR-4 namespace is recommended for
 * forward compatibility with Twig 3.x and beyond.
 *
 * This command is REPORT-ONLY — it never modifies any file.
 *
 * Usage:
 *   php bin/console prime:migrate:twig --dir=src/
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
            ->setDescription('Scan for Twig 1.x Twig_* legacy class references (report-only)')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:twig</info> command scans PHP source files for references to the
Twig 1.x <comment>Twig_*</comment> class naming convention.

The <comment>se7enxweb/twig</comment> package provides compatibility shims for these names, so
existing code continues to work. However, migrating to the modern <info>Twig\…</info>
PSR-4 namespace is recommended for forward compatibility.

Common migrations:
  <comment>\Twig_Extension</comment>         →  <info>Twig\Extension\AbstractExtension</info>
  <comment>\Twig_SimpleFilter</comment>      →  <info>Twig\TwigFilter</info>
  <comment>\Twig_SimpleFunction</comment>    →  <info>Twig\TwigFunction</info>
  <comment>\Twig_SimpleTest</comment>        →  <info>Twig\TwigTest</info>
  <comment>\Twig_Environment</comment>       →  <info>Twig\Environment</info>
  <comment>\Twig_Loader_Filesystem</comment> →  <info>Twig\Loader\FilesystemLoader</info>

<comment>This command never modifies any file.</comment>

  <info>php bin/console prime:migrate:twig --dir=src/</info>
HELP)
            ->addDirOption()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->resolveDir($input);

        $this->writeHeading($output, 'Twig Legacy Class Reference Scanner');
        $output->writeln(sprintf('Scanning: <comment>%s</comment>', $dir));
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
                    $this->writeIssueWithSuggestion(
                        $output,
                        $issue['line'],
                        '\\' . $issue['class'],
                        $issue['suggestion']
                    );
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
        $output->writeln('');

        return 1;
    }
}
