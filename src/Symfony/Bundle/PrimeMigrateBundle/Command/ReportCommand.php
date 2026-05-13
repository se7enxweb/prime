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
 * prime:migrate:report
 *
 * Generates a comprehensive migration status report combining all available
 * scans. Supports text (stdout), HTML (file), and JSON (file or stdout)
 * output formats.
 *
 * This command is 100% read-only — it never modifies, deletes, or renames
 * any source file regardless of output format.
 *
 * Usage:
 *   php bin/console prime:migrate:report --dir=src/
 *   php bin/console prime:migrate:report --dir=src/ --format=html --output=migration-report.html
 *   php bin/console prime:migrate:report --dir=src/ --format=json --output=migration-report.json
 *   php bin/console prime:migrate:report --dir=src/ --format=json   # JSON to stdout
 *
 * @author 7x <info@se7enx.com>
 */
class ReportCommand extends AbstractMigrateCommand
{
    use ScannerTrait;

    protected function configure(): void
    {
        $this
            ->setName('prime:migrate:report')
            ->setDescription('Generate a full migration status report (text/HTML/JSON) — read-only')
            ->setHelp(<<<'HELP'
The <info>prime:migrate:report</info> command runs all available migration scans and
generates a comprehensive status report. Use it to get a full picture of
the migration effort before starting, or to share with your team.

<comment>Text report to stdout (default):</comment>
  <info>php bin/console prime:migrate:report --dir=src/</info>

<comment>HTML report saved to a file:</comment>
  <info>php bin/console prime:migrate:report --dir=src/ --format=html --output=migration-report.html</info>

<comment>JSON report for CI pipeline integration:</comment>
  <info>php bin/console prime:migrate:report --dir=src/ --format=json --output=report.json</info>
  <info>php bin/console prime:migrate:report --dir=src/ --format=json</info>   # JSON to stdout

Use <comment>--verbose</comment> to include every affected line in the text/HTML output.

<comment>This command is 100% read-only — it never modifies any source file.</comment>
HELP)
            ->addDirOption()
            ->addOption(
                'format',
                null,
                InputOption::VALUE_REQUIRED,
                'Output format: text, html, or json',
                'text'
            )
            ->addOption(
                'output',
                null,
                InputOption::VALUE_REQUIRED,
                'Write report to this file instead of stdout (required for html format)'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir    = $this->resolveDir($input);
        $format = strtolower($input->getOption('format'));
        $outFile = $input->getOption('output');

        if (!in_array($format, ['text', 'html', 'json'], true)) {
            $output->writeln(sprintf('<error>Unknown format "%s". Use text, html, or json.</error>', $format));
            return 2;
        }

        if ($format === 'html' && $outFile === null) {
            $output->writeln('<error>The html format requires --output=<filename>.</error>');
            return 2;
        }

        $verbose = $output->isVerbose();

        // ── Collect all scan data ─────────────────────────────────────────────

        $output->writeln('<info>Scanning …</info>');

        $nullable    = $this->scanFiles($this->phpFiles($dir),  [self::class, 'scanNullable'],    $dir);
        $forms       = $this->scanFiles($this->phpFiles($dir),  [self::class, 'scanForms'],        $dir);
        $constraints = $this->scanFiles($this->phpFiles($dir),  [self::class, 'scanConstraints'],  $dir);
        $yaml        = $this->scanFiles($this->yamlFiles($dir), [self::class, 'scanYaml'],         $dir);
        $twig        = $this->scanFiles($this->phpFiles($dir),  [self::class, 'scanTwig'],         $dir);

        $data = [
            'generated'  => date('Y-m-d H:i:s'),
            'php'        => PHP_VERSION,
            'directory'  => $dir,
            'scans'      => [
                'nullable'    => ['label' => 'Implicit nullable types',   'severity' => 'required', 'results' => $nullable],
                'forms'       => ['label' => 'Form type string aliases',  'severity' => 'required', 'results' => $forms],
                'constraints' => ['label' => 'Reserved constraint names', 'severity' => 'required', 'results' => $constraints],
                'yaml'        => ['label' => 'YAML object tags',          'severity' => 'required', 'results' => $yaml],
                'twig'        => ['label' => 'Twig legacy classes',       'severity' => 'advisory', 'results' => $twig],
            ],
        ];

        // Compute totals
        $grandTotal = 0;
        foreach ($data['scans'] as &$scan) {
            $scan['issues'] = $this->countIssues($scan['results']);
            $scan['files']  = $this->countFiles($scan['results']);
            $grandTotal    += $scan['issues'];
        }
        unset($scan);

        $data['total_issues'] = $grandTotal;

        // ── Render ────────────────────────────────────────────────────────────

        switch ($format) {
            case 'json':
                $this->renderJson($data, $outFile, $output);
                break;
            case 'html':
                $this->renderHtml($data, $outFile, $output, $verbose);
                break;
            default:
                $this->renderText($data, $output, $verbose);
        }

        return $grandTotal > 0 ? 1 : 0;
    }

    // ── Text renderer ─────────────────────────────────────────────────────────

    private function renderText(array $data, OutputInterface $output, bool $verbose): void
    {
        $output->writeln('');
        $output->writeln('<info>7x Prime Migration Status Report</info>');
        $output->writeln(str_repeat('=', 33));
        $output->writeln(sprintf('Generated:  %s', $data['generated']));
        $output->writeln(sprintf('PHP:        %s', $data['php']));
        $output->writeln(sprintf('Directory:  %s', $data['directory']));
        $output->writeln('');
        $output->writeln('SUMMARY');
        $output->writeln('-------');

        foreach ($data['scans'] as $scan) {
            $issues   = $scan['issues'];
            $files    = $scan['files'];
            $severity = strtoupper($scan['severity']);

            if ($issues === 0) {
                $status = '<info>[OK]</info>';
                $detail = '  0 issues';
            } elseif ($scan['severity'] === 'required') {
                $status = '<error>[ACTION REQUIRED]</error>';
                $detail = sprintf('%3d %s in %2d %s', $issues, $this->issueWord($issues), $files, $files === 1 ? 'file ' : 'files');
            } else {
                $status = '<comment>[ADVISORY]</comment>';
                $detail = sprintf('%3d %s in %2d %s', $issues, $this->issueWord($issues), $files, $files === 1 ? 'file ' : 'files');
            }

            $label = $scan['label'];
            $dots  = str_repeat('.', max(1, 32 - mb_strlen($label)));
            $output->writeln(sprintf('  %s %s %s  %s', $label, $dots, $detail, $status));
        }

        $output->writeln('');

        if ($data['total_issues'] === 0) {
            $output->writeln('<info>OVERALL STATUS: MIGRATION COMPLETE — no issues found.</info>');
        } else {
            $output->writeln(sprintf(
                '<comment>OVERALL STATUS: MIGRATION INCOMPLETE — %d %s require attention.</comment>',
                $data['total_issues'],
                $this->issueWord($data['total_issues'])
            ));

            if ($data['scans']['nullable']['issues'] > 0) {
                $output->writeln('');
                $output->writeln('Run <info>php bin/console prime:migrate:nullable --fix --dry-run --dir=src/</info> to preview');
                $output->writeln('the auto-fix for nullable types, then <info>--fix</info> to apply it.');
            }
        }

        if ($verbose) {
            $output->writeln('');
            $output->writeln(str_repeat('─', 60));
            $output->writeln('DETAIL');
            $output->writeln(str_repeat('─', 60));

            foreach ($data['scans'] as $key => $scan) {
                if ($scan['issues'] === 0) {
                    continue;
                }
                $output->writeln('');
                $output->writeln(sprintf('<comment>%s</comment>', strtoupper($scan['label'])));

                foreach ($scan['results'] as $relPath => $issues) {
                    $this->writeFile($output, $relPath);
                    foreach ($issues as $issue) {
                        $this->writeIssue($output, $issue['line'], $issue['snippet']);
                    }
                }
            }
        }

        $output->writeln('');
    }

    // ── JSON renderer ─────────────────────────────────────────────────────────

    private function renderJson(array $data, ?string $outFile, OutputInterface $output): void
    {
        // Strip the nested 'results' arrays to keep the JSON payload clean;
        // include per-file/per-issue detail only if --verbose.
        $export = [
            'generated'    => $data['generated'],
            'php'          => $data['php'],
            'directory'    => $data['directory'],
            'total_issues' => $data['total_issues'],
            'scans'        => [],
        ];

        foreach ($data['scans'] as $key => $scan) {
            $export['scans'][$key] = [
                'label'    => $scan['label'],
                'severity' => $scan['severity'],
                'issues'   => $scan['issues'],
                'files'    => $scan['files'],
                'detail'   => [],
            ];

            foreach ($scan['results'] as $relPath => $issues) {
                $export['scans'][$key]['detail'][$relPath] = array_map(static function (array $i): array {
                    return ['line' => $i['line'], 'snippet' => $i['snippet']];
                }, $issues);
            }
        }

        $json = json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($outFile !== null) {
            file_put_contents($outFile, $json . "\n");
            $output->writeln(sprintf('<info>JSON report written to: %s</info>', $outFile));
        } else {
            $output->writeln($json);
        }
    }

    // ── HTML renderer ─────────────────────────────────────────────────────────

    private function renderHtml(array $data, string $outFile, OutputInterface $output, bool $verbose): void
    {
        $rows = '';
        foreach ($data['scans'] as $scan) {
            $issues = $scan['issues'];
            $files  = $scan['files'];

            if ($issues === 0) {
                $badge  = '<span class="badge ok">OK</span>';
                $detail = '—';
            } elseif ($scan['severity'] === 'required') {
                $badge  = '<span class="badge required">ACTION REQUIRED</span>';
                $detail = sprintf('%d %s in %d %s', $issues, $this->issueWord($issues), $files, $files === 1 ? 'file' : 'files');
            } else {
                $badge  = '<span class="badge advisory">ADVISORY</span>';
                $detail = sprintf('%d %s in %d %s', $issues, $this->issueWord($issues), $files, $files === 1 ? 'file' : 'files');
            }

            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>' . "\n",
                htmlspecialchars($scan['label'], ENT_QUOTES, 'UTF-8'),
                $detail,
                $badge
            );
        }

        // Verbose detail section
        $detailHtml = '';
        if ($verbose) {
            foreach ($data['scans'] as $key => $scan) {
                if ($scan['issues'] === 0) {
                    continue;
                }
                $detailHtml .= sprintf('<h2>%s</h2>', htmlspecialchars($scan['label'], ENT_QUOTES, 'UTF-8'));
                foreach ($scan['results'] as $relPath => $issues) {
                    $detailHtml .= sprintf('<h3>%s</h3><ul>', htmlspecialchars($relPath, ENT_QUOTES, 'UTF-8'));
                    foreach ($issues as $issue) {
                        $detailHtml .= sprintf(
                            '<li><code>Line %d:</code> %s</li>',
                            $issue['line'],
                            htmlspecialchars(rtrim($issue['snippet']), ENT_QUOTES, 'UTF-8')
                        );
                    }
                    $detailHtml .= '</ul>';
                }
            }
        }

        $status     = $data['total_issues'] === 0 ? 'COMPLETE' : 'INCOMPLETE';
        $statusCls  = $data['total_issues'] === 0 ? 'complete' : 'incomplete';
        $totalLabel = $data['total_issues'] === 0
            ? 'No issues found — migration looks complete.'
            : sprintf('%d %s require attention.', $data['total_issues'], $this->issueWord($data['total_issues']));

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>7x Prime Migration Status Report</title>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
         max-width: 900px; margin: 40px auto; padding: 0 20px; color: #333; }
  h1   { border-bottom: 2px solid #0070c9; padding-bottom: 10px; }
  h2   { margin-top: 2em; color: #555; }
  h3   { color: #333; font-size: 0.95em; font-family: monospace; }
  table { border-collapse: collapse; width: 100%; margin: 1em 0; }
  th, td { padding: 8px 14px; text-align: left; border-bottom: 1px solid #ddd; }
  th   { background: #f5f5f5; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 3px;
           font-size: 0.82em; font-weight: bold; white-space: nowrap; }
  .ok       { background: #d4edda; color: #155724; }
  .required { background: #f8d7da; color: #721c24; }
  .advisory { background: #fff3cd; color: #856404; }
  .status { padding: 14px 18px; border-radius: 4px; margin: 1.5em 0;
             font-weight: bold; font-size: 1.05em; }
  .complete   { background: #d4edda; color: #155724; }
  .incomplete { background: #f8d7da; color: #721c24; }
  code { background: #f4f4f4; padding: 1px 4px; border-radius: 3px; font-size: 0.9em; }
  ul   { font-family: monospace; font-size: 0.88em; }
  li   { padding: 2px 0; }
  .meta { color: #888; font-size: 0.9em; margin-bottom: 1em; }
</style>
</head>
<body>
<h1>7x Prime Migration Status Report</h1>
<p class="meta">
  Generated: {$data['generated']}<br>
  PHP: {$data['php']}<br>
  Directory: <code>{$data['directory']}</code>
</p>

<div class="status {$statusCls}">OVERALL STATUS: {$status} — {$totalLabel}</div>

<h2>Summary</h2>
<table>
  <thead><tr><th>Check</th><th>Findings</th><th>Status</th></tr></thead>
  <tbody>
{$rows}  </tbody>
</table>
{$detailHtml}
</body>
</html>
HTML;

        file_put_contents($outFile, $html);
        $output->writeln(sprintf('<info>HTML report written to: %s</info>', $outFile));
        $output->writeln(sprintf('Open in browser: <comment>file://%s</comment>', realpath($outFile) ?: $outFile));
    }
}
