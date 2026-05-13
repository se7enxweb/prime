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
 * prime:migrate:yaml
 *
 * Scans YAML files for !php/object: and !!php/object: tags. In 7x Prime,
 * YAML deserialisation of PHP objects is blocked by passing
 * ['allowed_classes' => false] to unserialize(). Any YAML that relied on
 * this feature will no longer produce PHP objects at parse time.
 *
 * This command is REPORT-ONLY — it never modifies any file.
 *
 * Usage:
 *   php bin/console prime:migrate:yaml
 *   php bin/console prime:migrate:yaml --dir=app/config
 *   php bin/console prime:migrate:yaml --dir=app/fixtures
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
            ->setDescription('Scan YAML files for !php/object: PHP object deserialisation tags (report-only)')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:yaml</info> command scans YAML files for PHP object deserialisation
tags (<comment>!php/object:</comment> and <comment>!!php/object:</comment>).

In 7x Prime, <comment>Yaml::parse()</comment> passes <comment>['allowed_classes' => false]</comment> to
<comment>unserialize()</comment> even when <comment>$objectSupport = true</comment> is set. YAML that previously
produced PHP objects will now return <comment>false</comment> (or throw, depending on the caller).

Replace PHP-object YAML round-trips with scalar/array data and reconstruct
objects in your application code. See <comment>MIGRATION.md — Step 7</comment>.

<comment>This command never modifies any file.</comment>

  <info>php bin/console prime:migrate:yaml --dir=app/config</info>
  <info>php bin/console prime:migrate:yaml --dir=app/fixtures</info>
HELP)
            ->addDirOption()
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $this->resolveDir($input);

        $this->writeHeading($output, 'YAML PHP Object Deserialisation Scanner');
        $output->writeln(sprintf('Scanning: <comment>%s</comment>', $dir));
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
        $output->writeln('These values will <error>no longer</error> deserialise to PHP objects in 7x Prime.');
        $output->writeln('Replace with scalar/array data and reconstruct objects in application code.');
        $output->writeln('');
        $output->writeln('Reference: <comment>MIGRATION.md — Step 7 (YAML Object Deserialisation)</comment>');
        $output->writeln('');

        return 1;
    }
}
