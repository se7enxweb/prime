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
 * prime:migrate:forms
 *
 * Scans PHP source files for string-based form type names — the Symfony 2.3–2.7
 * API deprecated in Symfony 2.8 and incompatible with Symfony 3.0+.
 *
 * 7x Prime retains the string aliases for backward compatibility but emits
 * deprecation notices. This command helps you locate them so you can replace
 * each string alias with the corresponding FQCN.
 *
 * This command is REPORT-ONLY — it never modifies any file.
 *
 * Usage:
 *   php bin/console prime:migrate:forms --dir=src/
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
            ->setDescription('Scan for string-based form type names (report-only, no files modified)')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:forms</info> command scans your PHP source for the Symfony 2.3–2.7
string-based form type API:

  <comment>->add('name', 'text')</comment>     →  <info>->add('name', TextType::class)</info>
  <comment>->add('age',  'integer')</comment>  →  <info>->add('age',  IntegerType::class)</info>

These string aliases were deprecated in Symfony 2.8 and are not available in
Symfony 3.0+. 7x Prime keeps them for backward compatibility but logs deprecations.

<comment>This command never modifies any file.</comment> Apply the fixes manually using the
FQCN mapping table in MIGRATION.md — Step 9 (Form Type API).

  <info>php bin/console prime:migrate:forms --dir=src/</info>
HELP)
            ->addDirOption()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->resolveDir($input);

        $this->writeHeading($output, 'Form Type String Alias Scanner');
        $output->writeln(sprintf('Scanning: <comment>%s</comment>', $dir));
        $output->writeln('');

        $results    = $this->scanFiles($this->phpFiles($dir), [self::class, 'scanForms'], $dir);
        $totalFiles = $this->countFiles($results);
        $totalIssues = $this->countIssues($results);

        if ($totalIssues === 0) {
            $output->writeln('<info>No string form type aliases found. All form types use FQCN.</info>');
            $output->writeln('');
            return 0;
        }

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
        $output->writeln('');
        $output->writeln('Reference: <comment>MIGRATION.md — Step 9 (Form Type API)</comment> for the complete FQCN mapping table.');
        $output->writeln('Apply fixes manually — add the corresponding <info>use</info> statements and replace each string alias.');
        $output->writeln('');

        return 1;
    }
}
