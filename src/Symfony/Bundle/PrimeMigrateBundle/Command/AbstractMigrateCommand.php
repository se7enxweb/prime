<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeMigrateBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * AbstractMigrateCommand — shared scanning utilities for prime:migrate:* commands.
 *
 * All commands in this namespace extend this class so that:
 *  - The --dir option is defined and resolved in one place.
 *  - PHP and YAML file iterators are consistent across all commands.
 *  - Output helpers (section headers, issue lines, summary lines) produce a
 *    uniform look regardless of which sub-command is running.
 *
 * SAFETY CONTRACT
 * ──────────────
 * Every method in this class is purely read-oriented. No method writes,
 * deletes, or renames any file on disk. Subclasses that need write access
 * (NullableCommand --fix) must implement that logic themselves, gated behind
 * an explicit --fix flag check, and MUST honour a --dry-run guard before
 * any file_put_contents() call. This guarantees that any invocation of a
 * prime:migrate:* command without an explicit --fix flag is a zero-side-effect
 * read-only operation.
 *
 * @author 7x <info@se7enx.com>
 */
abstract class AbstractMigrateCommand extends Command
{
    // ── Option names ─────────────────────────────────────────────────────────

    protected const OPT_DIR = 'dir';

    // ── Output colour tags ────────────────────────────────────────────────────

    protected const TAG_OK      = '<info>';
    protected const TAG_WARN    = '<comment>';
    protected const TAG_ERROR   = '<error>';
    protected const TAG_CLOSE   = '</info>';
    protected const TAG_WARN_C  = '</comment>';
    protected const TAG_ERROR_C = '</error>';

    // ── Shared option registration ────────────────────────────────────────────

    /**
     * Adds the --dir option to the command definition.
     *
     * Returns $this so callers can continue the fluent configure() chain.
     */
    protected function addDirOption(): static
    {
        $this->addOption(
            self::OPT_DIR,
            null,
            InputOption::VALUE_REQUIRED,
            'Directory to scan (relative to the project root or absolute)',
            'src'
        );

        return $this;
    }

    // ── Directory resolution ──────────────────────────────────────────────────

    /**
     * Resolves and validates the --dir option value.
     *
     * Accepts:
     *   - Absolute paths          /var/www/myapp/src
     *   - Paths relative to cwd   src  /  src/MyBundle
     *
     * @throws \InvalidArgumentException if the resolved path is not a readable directory.
     */
    protected function resolveDir(InputInterface $input): string
    {
        $raw = $input->getOption(self::OPT_DIR);

        // Absolute path — use as-is.
        if (is_dir($raw)) {
            return rtrim(realpath($raw), DIRECTORY_SEPARATOR);
        }

        // Relative path — resolve from cwd (where bin/console is invoked).
        $resolved = getcwd() . DIRECTORY_SEPARATOR . ltrim($raw, DIRECTORY_SEPARATOR);
        if (is_dir($resolved)) {
            return rtrim(realpath($resolved), DIRECTORY_SEPARATOR);
        }

        throw new \InvalidArgumentException(sprintf(
            'The --dir value "%s" does not resolve to a readable directory. '
            . 'Checked: "%s" and "%s".',
            $raw,
            $raw,
            $resolved
        ));
    }

    // ── File iterators ────────────────────────────────────────────────────────

    /**
     * Returns all *.php files under $dir as an iterator of \SplFileInfo objects.
     *
     * Files that cannot be read (permissions) are silently skipped.
     */
    protected function phpFiles(string $dir): \Iterator
    {
        return $this->filesWithExtension($dir, 'php');
    }

    /**
     * Returns all *.yml and *.yaml files under $dir.
     */
    protected function yamlFiles(string $dir): \Iterator
    {
        return $this->filesWithExtension($dir, ['yml', 'yaml']);
    }

    /**
     * Generic file iterator filtered by one or more extensions.
     *
     * @param string|string[] $extensions
     */
    protected function filesWithExtension(string $dir, $extensions): \Iterator
    {
        $extensions = (array) $extensions;
        $flags      = \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::FOLLOW_SYMLINKS;

        try {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, $flags),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );
        } catch (\UnexpectedValueException $e) {
            // Unreadable directory — return empty iterator.
            return new \EmptyIterator();
        }

        return new \CallbackFilterIterator($it, static function (\SplFileInfo $f) use ($extensions): bool {
            return $f->isFile()
                && $f->isReadable()
                && in_array(strtolower($f->getExtension()), $extensions, true);
        });
    }

    // ── Output helpers ────────────────────────────────────────────────────────

    /**
     * Prints a section heading underlined with '=' characters.
     *
     *   Implicit Nullable Type Scanner
     *   ==============================
     */
    protected function writeHeading(OutputInterface $output, string $title): void
    {
        $output->writeln('');
        $output->writeln($title);
        $output->writeln(str_repeat('=', mb_strlen($title)));
    }

    /**
     * Prints a file path label.
     *
     *   src/MyBundle/Controller/ArticleController.php
     */
    protected function writeFile(OutputInterface $output, string $relativePath): void
    {
        $output->writeln(sprintf(' <comment>%s</comment>', $relativePath));
    }

    /**
     * Prints a single issue line with line number and content snippet.
     *
     *   Line 42:  public function setExpires(\DateTime $date = null)
     */
    protected function writeIssue(OutputInterface $output, int $line, string $snippet): void
    {
        $output->writeln(sprintf(
            '   Line %d:  %s',
            $line,
            rtrim($snippet)
        ));
    }

    /**
     * Prints a "Line N:  content  →  suggestion" issue line.
     */
    protected function writeIssueWithSuggestion(
        OutputInterface $output,
        int $line,
        string $snippet,
        string $suggestion
    ): void {
        $output->writeln(sprintf(
            '   Line %d:  %s  <info>→ %s</info>',
            $line,
            rtrim($snippet),
            $suggestion
        ));
    }

    /**
     * Returns a short path relative to the scanned directory root.
     */
    protected function relativePath(string $absolutePath, string $baseDir): string
    {
        $base = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolutePath, $base)) {
            return substr($absolutePath, strlen($base));
        }

        return $absolutePath;
    }

    /**
     * Pluralises "issue" / "issues" correctly.
     */
    protected function issueWord(int $count): string
    {
        return $count === 1 ? 'issue' : 'issues';
    }

    /**
     * Pads a dots row for the check-style summary output.
     *
     *   [SCAN] Implicit nullable types ................... 14 issues found
     */
    protected function writeScanRow(OutputInterface $output, string $label, int $count): void
    {
        $left   = sprintf(' [SCAN] %s ', $label);
        $right  = sprintf('% 3d %s found', $count, $this->issueWord($count));
        $dots   = str_repeat('.', max(1, 52 - mb_strlen($left) - mb_strlen($right)));
        $colour = $count > 0 ? '<comment>' : '<info>';
        $close  = $count > 0 ? '</comment>' : '</info>';

        $output->writeln($colour . $left . $dots . ' ' . $right . $close);
    }
}
