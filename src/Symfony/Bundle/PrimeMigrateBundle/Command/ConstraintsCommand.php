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
 * This command is REPORT-ONLY — it never modifies any file.
 *
 * Usage:
 *   php bin/console prime:migrate:constraints --dir=src/
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
            ->setDescription('Scan for reserved-keyword Validator constraint names (report-only)')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:constraints</info> command scans for usages of the PHP 8 reserved
keyword constraint class names:

  <comment>Constraints\True</comment>  →  <info>Constraints\IsTrue</info>
  <comment>Constraints\False</comment> →  <info>Constraints\IsFalse</info>
  <comment>Constraints\Null</comment>  →  <info>Constraints\IsNull</info>

7x Prime provides <comment>class_alias()</comment> shims so the old names still work at runtime,
but PHP code that <comment>use</comment>s them may trigger deprecation notices or analysis tool
warnings. YAML / XML constraint configuration does NOT need to be updated.

<comment>This command never modifies any file.</comment>

  <info>php bin/console prime:migrate:constraints --dir=src/</info>
HELP)
            ->addDirOption()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->resolveDir($input);

        $this->writeHeading($output, 'Validator Reserved Keyword Constraint Scanner');
        $output->writeln(sprintf('Scanning: <comment>%s</comment>', $dir));
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
        $output->writeln('');
        $output->writeln('Migrate to:');
        $output->writeln('  <comment>Constraints\\True</comment>  →  <info>Constraints\\IsTrue</info>');
        $output->writeln('  <comment>Constraints\\False</comment> →  <info>Constraints\\IsFalse</info>');
        $output->writeln('  <comment>Constraints\\Null</comment>  →  <info>Constraints\\IsNull</info>');
        $output->writeln('');
        $output->writeln('YAML / XML configuration files do not need to be updated.');
        $output->writeln('The old names continue to work via <comment>class_alias()</comment> in 7x Prime.');
        $output->writeln('');

        return 1;
    }
}
