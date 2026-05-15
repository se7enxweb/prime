<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeMigrateBundle\Command;

/**
 * ScannerTrait — stateless static scanning methods shared across all migrate commands.
 *
 * Each method:
 *   - Takes a file path and its text content.
 *   - Returns an array of issue records: [ ['line' => int, 'snippet' => string, ...] ]
 *   - Never writes, modifies, or deletes anything.
 *
 * The methods are intentionally static so they can be called from both command
 * execute() methods (interactive) and from ReportCommand (batch).
 *
 * @author 7x <info@se7enx.com>
 */
trait ScannerTrait
{
    // ── Implicit nullable type scanner ────────────────────────────────────────

    /**
     * Scans PHP source content for implicit nullable parameter declarations.
     *
     * Detects the PHP 8.4+ deprecated pattern:
     *   SomeType $param = null           ← implicit nullable
     *
     * Returns an array of:
     *   [ 'line' => int, 'snippet' => string, 'param' => string, 'type' => string ]
     *
     * The 'type' value is the raw type-hint string so NullableCommand can
     * prepend '?' when applying a fix.
     *
     * Excluded patterns (do not flag):
     *   ?SomeType $param = null          ← already explicitly nullable
     *   SomeType|null $param = null      ← union type (PHP 8.0+)
     *   SomeType|AnotherType $param = null  ← union type
     *   mixed $param = null              ← 'mixed' is inherently nullable
     *   null $param = null               ← nonsensical but won't break
     */
    public static function scanNullable(string $content): array
    {
        $issues = [];
        $lines  = explode("\n", $content);

        /*
         * We process the file line-by-line looking for function/method
         * signature lines that contain implicit nullable parameters.
         *
         * Pattern breakdown:
         *   (?<!\?)          — negative lookbehind: not preceded by '?'
         *   (?<![|&])        — not preceded by '|' or '&' (union/intersection)
         *   (?<!\s)          — gives us the position just after any type start
         *
         * We use a positive-lookahead form instead for clarity:
         *
         *   \b(TYPEHINT)\s+(\$VARNAME)\s*=\s*null\b
         *
         * where TYPEHINT does NOT start with '?' and is NOT preceded by '|'/'&'.
         *
         * The regex is applied per-line so line numbers are accurate.
         */

        // Matches a type hint followed by $var = null
        // Group 1: the type-hint string (without leading ?)
        // Group 2: the variable name
        $re = '~
            (?<!\?)             # not already nullable
            (?<![|&])           # not preceded by | or & (union or intersection type)
            \b
            (                   # capture the type hint
                (?:\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*)
                |array|string|int|float|bool|callable|iterable|object|self|static|parent
            )
            \s+
            (\$[A-Za-z_][A-Za-z0-9_]*)   # variable name
            \s*=\s*null                    # = null default
            \b
        ~x';

        // Scalar built-ins for which adding '?' makes no semantic sense or is wrong.
        // PHP visibility / modifier keywords that appear before property declarations
        // must NOT be treated as type hints (Bug: `protected $foo = null` was matched
        // with type=protected, producing the syntax error `protected ?$foo = null`).
        // Statement keywords that appear before `$var = null` assignments also excluded.
        $skipTypes = [
            'mixed', 'void', 'never', 'null', 'false', 'true',
            'public', 'protected', 'private', 'static', 'abstract', 'final', 'readonly',
            'return', 'echo', 'print', 'throw', 'yield',
        ];

        foreach ($lines as $idx => $line) {
            $lineNo = $idx + 1;

            // Skip comment lines and blank lines quickly.
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }

            if (preg_match_all($re, $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $type  = $m[1];
                    $param = $m[2];

                    // Skip excluded types.
                    if (in_array(strtolower($type), $skipTypes, true)) {
                        continue;
                    }

                    // Find where in the line this match starts.
                    $pos    = strpos($line, $m[0]);
                    $before = $pos > 0 ? $line[$pos - 1] : ' ';

                    // Skip union/intersection types.
                    if ($before === '|' || $before === '&') {
                        continue;
                    }

                    // Bug fix: handle `?\ClassName` false positives.
                    // The regex optionally skips a leading `\` in the type group, so for
                    // `?\Exception $e = null` it captures `Exception` (starting after `\`).
                    // The char before the match-start is `\`, not `?`, so the `(?<!\?)`
                    // lookbehind in the regex lets it through. We correct that here:
                    //   char[-1] = `?`              → already nullable: ?Type
                    //   char[-1] = `\`, char[-2]=`?` → already nullable: ?\Type
                    //   char[-1] is a word char (a-z, A-Z, 0-9, _) → trailing part of a
                    //       FQCN (e.g. `\Environment` inside `?Twig\Environment`). The
                    //       full-name match was blocked by the `(?<!\?)` lookbehind, so
                    //       PCRE found this trailing segment instead. Skip it.
                    if ($before === '?') {
                        continue;
                    }
                    if ($before === '\\' && $pos > 1 && $line[$pos - 2] === '?') {
                        continue;
                    }
                    if (ctype_alnum($before) || $before === '_') {
                        continue; // inside a FQCN segment, not a standalone type
                    }

                    $issues[] = [
                        'line'    => $lineNo,
                        'snippet' => rtrim($line),
                        'type'    => $type,
                        'param'   => $param,
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Applies the nullable fix to a string of PHP source code.
     *
     * Replaces every un-prefixed `TypeHint $param = null` with
     * `?TypeHint $param = null`.
     *
     * This method is PURE (returns the fixed string, never writes a file).
     * The caller decides whether to write the result.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyNullableFix(string $content): array
    {
        $skipTypes = [
            'mixed', 'void', 'never', 'null', 'false', 'true',
            'public', 'protected', 'private', 'static', 'abstract', 'final', 'readonly',
            'return', 'echo', 'print', 'throw', 'yield',
        ];

        $re = '~
            (?<!\?)
            (?<![|&])
            \b
            (
                (?:\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*)
                |array|string|int|float|bool|callable|iterable|object|self|static|parent
            )
            (\s+)
            (\$[A-Za-z_][A-Za-z0-9_]*)
            (\s*=\s*null\b)
        ~x';

        $count = 0;

        // We need offset information to detect the `?\ClassName` false-positive, so
        // we use PREG_OFFSET_CAPTURE via a manual loop rather than preg_replace_callback.
        $lines  = explode("\n", $content);
        $result = [];

        foreach ($lines as $line) {
            if (preg_match_all($re, $line, $allMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                // Process matches in reverse order so offsets remain valid as we insert chars.
                $allMatches = array_reverse($allMatches);
                foreach ($allMatches as $m) {
                    $type      = $m[1][0];
                    $matchPos  = $m[0][1]; // byte offset of the full match

                    if (in_array(strtolower($type), $skipTypes, true)) {
                        continue;
                    }

                    $before    = $matchPos > 0 ? $line[$matchPos - 1] : ' ';

                    // Skip union/intersection.
                    if ($before === '|' || $before === '&') {
                        continue;
                    }

                    // Handle `?\ClassName`: regex matched `ClassName`, char before = `\`.
                    if ($before === '?') {
                        continue; // already nullable: ?Type
                    }
                    if ($before === '\\' && $matchPos > 1 && $line[$matchPos - 2] === '?') {
                        continue; // already nullable: ?\Type
                    }
                    // Trailing FQCN segment: e.g. `\Environment` inside `Twig\Environment`
                    // when the full-name match was blocked by `(?<!\?)`. Skip it.
                    if (ctype_alnum($before) || $before === '_') {
                        continue;
                    }

                    // For FQCN with leading `\` (non-nullable), the `\` is immediately
                    // before the match. Insert `?` before the `\`.
                    $insertPos = ($before === '\\') ? $matchPos - 1 : $matchPos;
                    $line = substr($line, 0, $insertPos) . '?' . substr($line, $insertPos);
                    ++$count;
                }
            }
            $result[] = $line;
        }

        $fixed = implode("\n", $result);

        return ['fixed' => $fixed, 'count' => $count];
    }

    // ── Form type string alias scanner ────────────────────────────────────────

    /**
     * Returns the canonical map of Symfony 2.x string form type aliases
     * to their FQCN equivalents.
     *
     * Used by FormsCommand and ReportCommand.
     *
     * @return array<string, string>
     */
    public static function formTypeMap(): array
    {
        return [
            'text'        => 'TextType::class',
            'textarea'    => 'TextareaType::class',
            'integer'     => 'IntegerType::class',
            'number'      => 'NumberType::class',
            'money'       => 'MoneyType::class',
            'email'       => 'EmailType::class',
            'password'    => 'PasswordType::class',
            'url'         => 'UrlType::class',
            'search'      => 'SearchType::class',
            'percent'     => 'PercentType::class',
            'range'       => 'RangeType::class',
            'checkbox'    => 'CheckboxType::class',
            'radio'       => 'RadioType::class',
            'choice'      => 'ChoiceType::class',
            'date'        => 'DateType::class',
            'datetime'    => 'DateTimeType::class',
            'time'        => 'TimeType::class',
            'birthday'    => 'BirthdayType::class',
            'file'        => 'FileType::class',
            'hidden'      => 'HiddenType::class',
            'submit'      => 'SubmitType::class',
            'button'      => 'ButtonType::class',
            'reset'       => 'ResetType::class',
            'collection'  => 'CollectionType::class',
            'repeated'    => 'RepeatedType::class',
            'entity'      => 'EntityType::class',
            'form'        => 'FormType::class',
            'locale'      => 'LocaleType::class',
            'language'    => 'LanguageType::class',
            'country'     => 'CountryType::class',
            'timezone'    => 'TimezoneType::class',
            'currency'    => 'CurrencyType::class',
        ];
    }

    /**
     * Scans PHP source content for string-based form type aliases.
     *
     * Detects:
     *   ->add('field', 'text')          → TextType::class
     *   ->add('field', 'email', [...])
     *   $builder->add('f', 'choice')
     *   createForm('form_type_name', ...)
     *   setType('text')
     *
     * Returns [ 'line' => int, 'snippet' => string, 'alias' => string, 'fqcn' => string ]
     */
    public static function scanForms(string $content): array
    {
        $issues  = [];
        $typeMap = self::formTypeMap();
        $aliases = implode('|', array_keys($typeMap));
        $lines   = explode("\n", $content);

        // Pattern: string alias in the TYPE position only.
        //
        // Handles two call shapes:
        //
        //   (A) ->add( 'fieldName', 'alias' )
        //       The type is specifically the SECOND argument to ->add().  We
        //       consume the opening `->add(` and the field-name string literal
        //       before matching the alias, so arbitrary comma-preceded strings
        //       (compact(), array(), setAttribute(), etc.) are never matched.
        //
        //   (B) createForm( 'alias', … )  /  setType( 'alias', … )
        //       The alias IS the first argument but follows a named function
        //       open-paren that is specific to form factory calls.
        //
        // The two shapes are joined with | so a single preg_match_all covers both.
        $re = '/'
            // Shape A: ->add( 'fieldName' , 'alias'
            . '(?:->add\s*\(\s*(?:\'[^\']*\'|"[^"]*")\s*,\s*)'
            . '[\'"]('. $aliases . ')[\'"](?=\s*(?:,|\)))'
            // Shape B: createForm( 'alias'  /  setType( 'alias'
            . '|(?:(?:createForm|setType)\s*\(\s*)'
            . '[\'"]('. $aliases . ')[\'"](?=\s*(?:,|\)))'
            . '/';

        foreach ($lines as $idx => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }
            if (preg_match_all($re, $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    // Group 1 = Shape A match, Group 2 = Shape B match (one is empty).
                    $alias = $m[1] !== '' ? $m[1] : $m[2];
                    $issues[] = [
                        'line'    => $idx + 1,
                        'snippet' => rtrim($line),
                        'alias'   => $alias,
                        'fqcn'    => $typeMap[$alias],
                    ];
                }
            }
        }

        return $issues;
    }

    // ── Form type FQCN namespace map ──────────────────────────────────────────

    /**
     * Returns the fully-qualified namespace for each form type alias.
     *
     * Used by applyFormsFix() to inject `use` statements.
     *
     * @return array<string, string>  alias → FQCN (without ::class suffix)
     */
    public static function formTypeNamespaceMap(): array
    {
        $coreNs = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\';
        $map    = [];

        foreach (self::formTypeMap() as $alias => $fqcn) {
            $className  = str_replace('::class', '', $fqcn);
            $map[$alias] = match ($alias) {
                'entity' => 'Symfony\\Bridge\\Doctrine\\Form\\Type\\EntityType',
                default  => $coreNs . $className,
            };
        }

        return $map;
    }

    /**
     * Applies the forms fix to a string of PHP source code.
     *
     * Replaces every string form type alias used as a form type name with its
     * FQCN short class constant (e.g. `'text'` → `TextType::class`) and
     * injects the corresponding `use` statements at the top of the file.
     *
     * This method is PURE (returns the fixed string, never writes a file).
     * The caller decides whether to write the result.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyFormsFix(string $content): array
    {
        $typeMap = self::formTypeMap();
        $nsMap   = self::formTypeNamespaceMap();
        $aliases = implode('|', array_keys($typeMap));

        // Same two-shape pattern as scanForms() — matches the TYPE position only.
        //
        // Capturing groups:
        //   Shape A: (1) = `->add( 'fieldName', ` prefix  (2) = alias  (3) = ''
        //   Shape B: (1) = `createForm( ` prefix           (2) = ''     (3) = alias
        //
        // Group 1 is always the prefix to reconstruct verbatim in the replacement.
        // Groups 2 and 3 are mutually exclusive; we take whichever is non-empty.
        $re = '/'
            // Shape A — capture prefix + field-name so they are preserved verbatim
            . '(->add\s*\(\s*(?:\'[^\']*\'|"[^"]*")\s*,\s*)'
            . '[\'"]('. $aliases . ')[\'"](?=\s*(?:,|\)))'
            // Shape B
            . '|((?:createForm|setType)\s*\(\s*)'
            . '[\'"]('. $aliases . ')[\'"](?=\s*(?:,|\)))'
            . '/';

        $count       = 0;
        $usedAliases = [];

        $fixed = preg_replace_callback(
            $re,
            static function (array $m) use ($typeMap, &$count, &$usedAliases): string {
                // Shape A: groups 1 (prefix) + 2 (alias)
                // Shape B: groups 3 (prefix) + 4 (alias)
                $prefix = $m[1] !== '' ? $m[1] : $m[3];
                $alias  = $m[2] !== '' ? $m[2] : $m[4];
                $usedAliases[$alias] = true;
                ++$count;

                return $prefix . $typeMap[$alias];
            },
            $content
        );

        if ($fixed === null || $count === 0) {
            return ['fixed' => $content, 'count' => 0, 'injected' => []];
        }

        // Determine which use statements need to be injected.
        $toImport = [];
        foreach (array_keys($usedAliases) as $alias) {
            $fqn = $nsMap[$alias] ?? null;
            if ($fqn === null) {
                continue;
            }
            // Skip if a use statement for this FQN already exists in the original content.
            if (strpos($content, 'use ' . $fqn) !== false) {
                continue;
            }
            $toImport[] = $fqn;
        }
        sort($toImport);

        if (!empty($toImport)) {
            $fixed = self::injectUseStatements($fixed, $toImport);
        }

        return ['fixed' => $fixed, 'count' => $count, 'injected' => $toImport];
    }

    /**
     * Inserts `use` statements into PHP source, sorted alphabetically.
     *
     * Placement preference:
     *   1. After the last existing `use` statement.
     *   2. After the `namespace` declaration (with a blank line separator).
     *   3. After the `<?php` opening tag (with a blank line separator).
     *   4. Prepended to the content if no anchor is found.
     *
     * @param string[] $fqns  Fully-qualified class names to import (without trailing ';')
     */
    private static function injectUseStatements(string $content, array $fqns): string
    {
        sort($fqns);

        $useLines = array_map(static fn (string $fqn): string => 'use ' . $fqn . ';', $fqns);

        $lines        = explode("\n", $content);
        $lastUseIdx   = -1;
        $namespaceIdx = -1;
        $phpOpenIdx   = -1;

        foreach ($lines as $idx => $line) {
            $t = ltrim($line);
            if ($phpOpenIdx === -1 && str_starts_with($t, '<?php')) {
                $phpOpenIdx = $idx;
            }
            if (str_starts_with($t, 'namespace ')) {
                $namespaceIdx = $idx;
            }
            // Only count top-level `use` statements (no leading whitespace).
            // Trait-use lines inside class bodies are indented and must be ignored.
            if (str_starts_with($line, 'use ') && str_ends_with(rtrim($line), ';')) {
                $lastUseIdx = $idx;
            }
        }

        if ($lastUseIdx >= 0) {
            array_splice($lines, $lastUseIdx + 1, 0, $useLines);
        } elseif ($namespaceIdx >= 0) {
            array_splice($lines, $namespaceIdx + 1, 0, array_merge([''], $useLines));
        } elseif ($phpOpenIdx >= 0) {
            array_splice($lines, $phpOpenIdx + 1, 0, array_merge([''], $useLines));
        } else {
            array_unshift($lines, ...$useLines);
        }

        return implode("\n", $lines);
    }

    // ── Validator reserved-keyword constraint scanner ─────────────────────────

    /**
     * Scans PHP source content for PHP-reserved Validator constraint names.
     *
     * Detects:
     *   use Symfony\Component\Validator\Constraints\True;
     *   use Symfony\Component\Validator\Constraints\False;
     *   use Symfony\Component\Validator\Constraints\Null;
     *   new True() / new False() / new Null()
     *   Constraints\True  / Constraints\False / Constraints\Null
     *
     * Returns [ 'line' => int, 'snippet' => string, 'old' => string, 'new' => string ]
     */
    public static function scanConstraints(string $content): array
    {
        $issues = [];
        $lines  = explode("\n", $content);
        $map    = [
            'True'  => 'IsTrue',
            'False' => 'IsFalse',
            'Null'  => 'IsNull',
        ];

        $re = '/\b(Constraints\\\\(True|False|Null)\b|\\buse\s+[^;]*\\\\(True|False|Null)\s*;|\\bnew\s+(True|False|Null)\s*\()/';

        foreach ($lines as $idx => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }
            if (preg_match_all($re, $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    // Determine which keyword was matched
                    $old = $m[2] ?: $m[3] ?: $m[4] ?: '';
                    if ($old === '' || !isset($map[$old])) {
                        continue;
                    }
                    $issues[] = [
                        'line'    => $idx + 1,
                        'snippet' => rtrim($line),
                        'old'     => $old,
                        'new'     => $map[$old],
                    ];
                }
            }
        }

        return $issues;
    }

    // ── YAML !php/object: scanner ─────────────────────────────────────────────

    /**
     * Scans YAML source content for PHP object deserialisation tags.
     *
     * Detects:
     *   !php/object: "O:4:..."
     *   !!php/object: "..."
     *
     * Returns [ 'line' => int, 'snippet' => string ]
     */
    public static function scanYaml(string $content): array
    {
        $issues = [];
        $lines  = explode("\n", $content);
        $re     = '/!{1,2}php\/object:/';

        foreach ($lines as $idx => $line) {
            if (preg_match($re, $line)) {
                $issues[] = [
                    'line'    => $idx + 1,
                    'snippet' => rtrim($line),
                ];
            }
        }

        return $issues;
    }

    // ── YAML auto-fixer ───────────────────────────────────────────────────────

    /**
     * Applies the YAML !php/object: fix to YAML source content.
     *
     * Strips the !php/object: or !!php/object: tag prefix, leaving the
     * serialised value as a plain YAML string. Any consuming code that
     * relied on the value being a PHP object must be updated to call
     * unserialize() explicitly.
     *
     * This method is PURE — it never writes a file.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyYamlFix(string $content): array
    {
        $count = 0;

        $fixed = preg_replace_callback(
            '/!{1,2}php\/object:\s*/',
            static function (array $m) use (&$count): string {
                ++$count;
                return '';
            },
            $content
        );

        return ['fixed' => $fixed ?? $content, 'count' => $count];
    }

    // ── Twig legacy class reference scanner ───────────────────────────────────

    /**
     * Scans PHP source content for Twig 1.x `Twig_*` class naming convention.
     *
     * Returns [ 'line' => int, 'snippet' => string, 'class' => string ]
     */
    public static function scanTwig(string $content): array
    {
        $issues = [];
        $lines  = explode("\n", $content);

        // Match Twig_Xxx class names (with optional leading backslash)
        $re = '/\\\\?(Twig_[A-Za-z_][A-Za-z0-9_]*)/';

        // Canonical migration map for display hints
        $twigMap = [
            'Twig_Extension'           => 'Twig\\Extension\\AbstractExtension',
            'Twig_SimpleFilter'        => 'Twig\\TwigFilter',
            'Twig_SimpleFunction'      => 'Twig\\TwigFunction',
            'Twig_SimpleTest'          => 'Twig\\TwigTest',
            'Twig_Environment'         => 'Twig\\Environment',
            'Twig_Loader_Filesystem'   => 'Twig\\Loader\\FilesystemLoader',
            'Twig_Loader_Array'        => 'Twig\\Loader\\ArrayLoader',
            'Twig_Filter_Method'       => 'Twig\\TwigFilter',
            'Twig_Function_Method'     => 'Twig\\TwigFunction',
            'Twig_Node'                => 'Twig\\Node\\Node',
            'Twig_Error_Runtime'       => 'Twig\\Error\\RuntimeError',
            'Twig_Error_Loader'        => 'Twig\\Error\\LoaderError',
            'Twig_Error_Syntax'        => 'Twig\\Error\\SyntaxError',
            'Twig_Template'            => 'Twig\\Template',
            'Twig_Extension_Core'      => 'Twig\\Extension\\CoreExtension',
            'Twig_Extension_Escaper'   => 'Twig\\Extension\\EscaperExtension',
            'Twig_Extension_Optimizer' => 'Twig\\Extension\\OptimizerExtension',
            'Twig_LoaderInterface'     => 'Twig\\Loader\\LoaderInterface',
            'Twig_Markup'              => 'Twig\\Markup',
        ];

        foreach ($lines as $idx => $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*')) {
                continue;
            }
            if (preg_match_all($re, $line, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $m) {
                    $cls     = $m[1];
                    $suggestion = $twigMap[$cls] ?? null;
                    $issues[] = [
                        'line'       => $idx + 1,
                        'snippet'    => rtrim($line),
                        'class'      => $cls,
                        'suggestion' => $suggestion,
                    ];
                }
            }
        }

        return $issues;
    }

    // ── Constraints auto-fixer ────────────────────────────────────────────────

    /**
     * Applies the constraints fix to a string of PHP source code.
     *
     * Replaces PHP-reserved constraint names with their Is* equivalents:
     *   Constraints\True  → Constraints\IsTrue
     *   Constraints\False → Constraints\IsFalse
     *   Constraints\Null  → Constraints\IsNull
     *   new True(         → new IsTrue(
     *   new False(        → new IsFalse(
     *   new Null(         → new IsNull(
     *
     * This method is PURE — it never writes a file.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyConstraintsFix(string $content): array
    {
        $count = 0;

        // Namespace-qualified: Constraints\True / Constraints\False / Constraints\Null
        $fixed = preg_replace_callback(
            '/Constraints\\\\(True|False|Null)\b/',
            static function (array $m) use (&$count): string {
                ++$count;
                return 'Constraints\\Is' . $m[1];
            },
            $content
        );

        // Bare: new True( / new False( / new Null(
        $fixed = preg_replace_callback(
            '/\bnew\s+(True|False|Null)\s*\(/',
            static function (array $m) use (&$count): string {
                ++$count;
                return 'new Is' . $m[1] . '(';
            },
            $fixed ?? $content
        );

        return ['fixed' => $fixed ?? $content, 'count' => $count];
    }

    // ── Twig auto-fixer ───────────────────────────────────────────────────────

    /**
     * Applies the Twig legacy name fix to a string of PHP source code.
     *
     * Replaces every known Twig_* class reference with its Twig\ PSR-4 equivalent.
     * References not in the known map are left unchanged.
     *
     * This method is PURE — it never writes a file.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyTwigFix(string $content): array
    {
        $count   = 0;
        $twigMap = [
            'Twig_Extension'           => 'Twig\\Extension\\AbstractExtension',
            'Twig_SimpleFilter'        => 'Twig\\TwigFilter',
            'Twig_SimpleFunction'      => 'Twig\\TwigFunction',
            'Twig_SimpleTest'          => 'Twig\\TwigTest',
            'Twig_Environment'         => 'Twig\\Environment',
            'Twig_Loader_Filesystem'   => 'Twig\\Loader\\FilesystemLoader',
            'Twig_Loader_Array'        => 'Twig\\Loader\\ArrayLoader',
            'Twig_Filter_Method'       => 'Twig\\TwigFilter',
            'Twig_Function_Method'     => 'Twig\\TwigFunction',
            'Twig_Node'                => 'Twig\\Node\\Node',
            'Twig_Error_Runtime'       => 'Twig\\Error\\RuntimeError',
            'Twig_Error_Loader'        => 'Twig\\Error\\LoaderError',
            'Twig_Error_Syntax'        => 'Twig\\Error\\SyntaxError',
            'Twig_Template'            => 'Twig\\Template',
            'Twig_Extension_Core'      => 'Twig\\Extension\\CoreExtension',
            'Twig_Extension_Escaper'   => 'Twig\\Extension\\EscaperExtension',
            'Twig_Extension_Optimizer' => 'Twig\\Extension\\OptimizerExtension',
            'Twig_LoaderInterface'     => 'Twig\\Loader\\LoaderInterface',
            'Twig_Markup'              => 'Twig\\Markup',
        ];

        $classes = implode('|', array_map('preg_quote', array_keys($twigMap), array_fill(0, count($twigMap), '/')));

        $fixed = preg_replace_callback(
            '/(\\\\?)(' . $classes . ')\b/',
            static function (array $m) use ($twigMap, &$count): string {
                $prefix = $m[1]; // optional leading backslash — preserved
                $cls    = $m[2];
                if (!isset($twigMap[$cls])) {
                    return $m[0];
                }
                ++$count;
                return $prefix . $twigMap[$cls];
            },
            $content
        );

        return ['fixed' => $fixed ?? $content, 'count' => $count];
    }
    // ── MockBuilder::setMethods() → onlyMethods() scanner ───────────────────

    /**
     * Scans for deprecated MockBuilder::setMethods() calls.
     * PHPUnit 10+ removed setMethods(); the replacement is onlyMethods().
     *
     * Returns [ 'line' => int, 'snippet' => string ]
     */
    public static function scanSetMethods(string $content): array
    {
        // Only match ->setMethods( if the file also contains getMockBuilder or createMock
        // to avoid false positives in production code (e.g. Route::setMethods())
        if (!str_contains($content, 'getMockBuilder') && !str_contains($content, 'createMock')) {
            return [];
        }
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (str_contains($line, '->setMethods(')) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces ->onlyMethods( with ->onlyMethods( throughout the file.
     * Special case:  is removed (null means "mock all methods",
     * which is now the default behaviour with getMockBuilder()).
     *
     * @return array{fixed: string, count: int}
     */
    public static function applySetMethodsFix(string $content): array
    {
        // Only fix files that have MockBuilder/createMock context to avoid
        // false positives in production code (e.g. Route::setMethods())
        if (!str_contains($content, 'getMockBuilder') && !str_contains($content, 'createMock')) {
            return ['fixed' => $content, 'count' => 0];
        }

        //  → remove the entire chained call (line-level)
        $fixed = preg_replace('/\s*->setMethods\(\s*null\s*\)/', '', $content, -1, $countNull);

        // ->onlyMethods([...]) or ->onlyMethods(['foo','bar']) → ->onlyMethods(...)
        $fixed = preg_replace('/->setMethods\(/', '->onlyMethods(', $fixed, -1, $countRename);

        $count = (int)$countNull + (int)$countRename;
        return ['fixed' => $fixed ?? $content, 'count' => $count];
    }

    // ── assertFileDoesNotExist() → assertFileDoesNotExist() scanner ────────────

    /**
     * Scans for removed PHPUnit assertFileDoesNotExist() calls (renamed in PHPUnit 9.1).
     *
     * Returns [ 'line' => int, 'snippet' => string ]
     */
    public static function scanAssertFileNotExists(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\bassertFileNotExists\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces assertFileDoesNotExist( → assertFileDoesNotExist(.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyAssertFileNotExistsFix(string $content): array
    {
        $fixed = preg_replace('/\bassertFileNotExists\s*\(/', 'assertFileDoesNotExist(', $content, -1, $count);
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── PHPUnit\Util\XML → native DOM scanner ────────────────────────────────

    /**
     * Scans for removed PHPUnit\Util\XML class usages (removed in PHPUnit 10).
     * The loadfile() call is replaceable with DOMDocument::load() + validate.
     *
     * Returns [ 'line' => int, 'snippet' => string ]
     */
    public static function scanUtilXml(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/PHPUnit(?:_Util_XML|\\\\Util\\\\XML)/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces PHPUnit\Util\XML::loadfile($file, false, false, true) with
     * a DOMDocument::load() equivalent that validates against XML schema.
     * Also removes the surrounding PHPUnit_Util_XML class_exists guard block.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyUtilXmlFix(string $content): array
    {
        // Replace the if/else guard that checks PHPUnit_Util_XML + calls one or the other
        // Pattern:
        //   if (class_exists('PHPUnit_Util_XML')) {
        //       \PHPUnit_Util_XML::loadfile($filePath, false, false, true);
        //   } else {
        //       \PHPUnit\Util\XML::loadfile($filePath, false, false, true);
        //   }
        $replacement = <<<'PHP'
$doc = new \DOMDocument();
            $this->assertTrue($doc->load($filePath), sprintf('"%s" is not a valid XML file.', $filePath));
PHP;

        $fixed = preg_replace(
            '/if\s*\(class_exists\([\'"]PHPUnit_Util_XML[\'"]\)\)\s*\{[^}]*\}\s*else\s*\{[^}]*\}/',
            rtrim($replacement),
            $content,
            -1,
            $count
        );

        if ((int)$count === 0) {
            // Fallback: just replace bare calls to either class
            $fixed = preg_replace(
                '/\\\\?PHPUnit(?:_Util_XML|\\\\Util\\\\XML)::loadfile\([^)]+\);/',
                '$doc = new \\DOMDocument(); $doc->load($filePath);',
                $content,
                -1,
                $count
            );
        }

        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── SplObjectStorage::contains() → offsetExists() scanner ───────────────

    /**
     * Scans for SplObjectStorage::contains() which is deprecated since PHP 8.5.
     * The replacement is offsetExists().
     *
     * Returns [ 'line' => int, 'snippet' => string ]
     */
    public static function scanSplContains(string $content): array
    {
        $issues = [];
        // Only scan files that actually use SplObjectStorage
        if (!str_contains($content, 'SplObjectStorage')) {
            return $issues;
        }
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\$\w+->contains\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces $foo->contains($x) with $foo->offsetExists($x) ONLY in files
     * that declare or use SplObjectStorage.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applySplContainsFix(string $content): array
    {
        if (!preg_match('/SplObjectStorage/', $content)) {
            return ['fixed' => $content, 'count' => 0];
        }
        $fixed = preg_replace('/(\$\w+)->contains\(/', '$1->offsetExists(', $content, -1, $count);
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── Implicit nullable parameter scanner ──────────────────────────────────

    /**
     * Scans for implicitly nullable parameters: `Type $param = null` where Type
     * is not already prefixed with `?`. PHP 8.4 deprecated this pattern.
     *
     * Returns [ 'line' => int, 'snippet' => string, 'param' => string ]
     */
    public static function scanImplicitNullable(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            // Match: SomeType $varName = null  (not preceded by ? or \)
            if (preg_match_all(
                '/(?<![?\\\\])\b([A-Z][A-Za-z0-9_\\\\]*|\bstring\b|\bint\b|\bfloat\b|\bbool\b|\barray\b|\bcallable\b|\biterable\b|\bobject\b)\s+(\$\w+)\s*=\s*null(?!\w)/',
                $line,
                $matches,
                PREG_SET_ORDER
            )) {
                foreach ($matches as $m) {
                    $issues[] = [
                        'line'    => $idx + 1,
                        'snippet' => rtrim($line),
                        'param'   => $m[2],
                    ];
                }
            }
        }
        return $issues;
    }

    /**
     * Adds `?` before non-nullable typed parameters that default to null.
     * Only modifies function/method signature lines.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyImplicitNullableFix(string $content): array
    {
        // Match function/method signature lines containing Type $var = null
        $fixed = preg_replace_callback(
            '/^([ \t]*(?:(?:abstract|final|static|public|protected|private)\s+)*function\s+\w+\s*\()(.*)(\)(?:\s*:\s*\S+)?\s*[{;]?\s*)$/m',
            static function (array $m): string {
                // Replace unqualified nullable params in the params list
                // (?<![?\]) ensures we don't re-add ? before already-nullable or backslash-prefixed types
                $params = preg_replace(
                    '/(?<![?\\\\])(\b(?:[A-Z][A-Za-z0-9_\\\\]*|string|int|float|bool|array|callable|iterable|object)\b)\s+(\$\w+)(\s*=\s*null)(?=\s*[,)])/',
                    '?$1 $2$3',
                    $m[2],
                    -1,
                    $count
                );
                return $m[1] . $params . $m[3];
            },
            $content,
            -1,
            $count
        );
        // $count from preg_replace_callback counts outer matches, not inner; use string diff
        $innerCount = substr_count($fixed ?? $content, '?') - substr_count($content, '?');
        return ['fixed' => $fixed ?? $content, 'count' => max(0, $innerCount)];
    }

    // ── assertEquals() null message deprecation scanner ─────────────────────

    /**
     * Scans for assertEquals/assertSame/etc calls passing null as the $message arg.
     * PHP 8+ requires $message to be string, passing null triggers TypeError.
     *
     * Returns [ 'line' => int, 'snippet' => string ]
     */
    public static function scanAssertNullMessage(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            // Detect: assert* call whose line ends with , null) or , null);
            // Use line-end pattern to avoid [^)]+ failing on nested parens.
            if (preg_match('/\$(?:this->|self::)assert\w+/', $line)
                && preg_match('/,\s*null\s*\)\s*;?\s*$/', $line)
            ) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces assertXxx(..., null) → assertXxx(..., '') for the message argument.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyAssertNullMessageFix(string $content): array
    {
        $lines = explode("\n", $content);
        $count = 0;
        foreach ($lines as &$line) {
            if (!preg_match('/\$(?:this->|self::)assert\w+/', $line)) {
                continue;
            }
            // Pattern 1: ..., null) at end of line
            if (preg_match('/,\s*null\s*\)\s*;?\s*$/', $line)) {
                $new = preg_replace('/,\s*null(\s*\)\s*;?\s*)$/', ", ''$1", $line);
                if ($new !== $line) { $line = $new; ++$count; continue; }
            }
            // Pattern 2: ..., null, somethingElse) — null as non-last message arg
            if (preg_match('/,\s*null\s*,/', $line)) {
                $new = preg_replace('/,\s*null\s*,/', ", '',", $line);
                if ($new !== $line) { $line = $new; ++$count; }
            }
        }
        return ['fixed' => implode("\n", $lines), 'count' => $count];
    }

    // ── at(N) → any() ────────────────────────────────────────────────────────

    /**
     * Scans for $this->at(N) invocation matchers removed in PHPUnit 10.
     * Replacement: $this->any() (or a more specific willReturn sequence if needed).
     */
    public static function scanAtMethod(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\$this->at\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces $this->at(N) with $this->any().
     * Note: this loses call-order verification – restructure tests for stricter ordering.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyAtMethodFix(string $content): array
    {
        $fixed = preg_replace('/\$this->at\s*\([^)]*\)/', '$this->any()', $content, -1, $count);
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── setAccessible(true) removal ──────────────────────────────────────────

    /**
     * Scans for ->setAccessible(true) which is a no-op since PHP 8.1 and
     * deprecated since PHP 8.5. Removing it is safe.
     */
    public static function scanSetAccessible(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/->setAccessible\s*\(\s*true\s*\)/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Removes entire lines containing ->setAccessible(true).
     * setAccessible(false) is intentionally NOT touched.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applySetAccessibleFix(string $content): array
    {
        $lines = explode("\n", $content);
        $out   = [];
        $count = 0;
        foreach ($lines as $line) {
            if (preg_match('/->setAccessible\s*\(\s*true\s*\)/', $line)) {
                ++$count;
                // Drop the line entirely
            } else {
                $out[] = $line;
            }
        }
        return ['fixed' => implode("\n", $out), 'count' => $count];
    }

    // ── assertContains(string, string) → assertStringContainsString ──────────

    /**
     * Scans for assertContains/assertNotContains where the needle is a string
     * literal and the haystack is NOT an array literal. PHPUnit 10 requires the
     * haystack to be Traversable|array; use assertStringContainsString for
     * string-in-string checks.
     *
     * Also detects the reverse: assertStringContainsString/assertStringNotContainsString
     * where the haystack is a known array-returning method call (e.g. getAlternatives()).
     */
    public static function scanAssertContainsString(string $content): array
    {
        $issues = [];
        // Known array-returning method suffixes that should use assertContains, not assertStringContainsString
        $arrayMethods = 'getAlternatives|getNames|getCountries|getLanguages|getLocales'
            . '|getCommands|getOptions|getArguments|getBundles|getKeys|getValues'
            . '|getTags|getExtensions|getPlugins|getFiles|getPaths|getItems|getList'
            . '|getResults|getErrors|getWarnings|getGroups|getRoles|getPermissions'
            . '|getParameter|getUsages|getPrefixes|getCurrencies|getGroups|getTemplates'
            . '|getMetadata|getClassNames|getMessages|getProperties|getMethods'
            . '|getConstraints|getAttributes|getHierarchy|getResourcesByType'
            . '|getResources|getNamespaces|getClasses|getDecoratedService|getBindings';
        // Known array variable names (plural nouns typically hold arrays)
        $arrayVarPattern = '/\$(?:countries|languages|locales|currencies|groups|resources'
            . '|prefixes|paths|files|items|keys|values|tags|options|choices|errors'
            . '|warnings|messages|results|classes|names|roles|bundles|extensions'
            . '|plugins|commands|arguments|attributes|constraints|properties|methods'
            . '|countryCodes|classCodes|usages|templates|namespaces|classNames'
            . '|decoratedServices|bindings)\b/';

        foreach (explode("\n", $content) as $idx => $line) {
            // Case A: assertContains/assertNotContains with string literal needle and non-array haystack
            // Exclude: explicit array literal   assertContains(x, [...]  or array(...)
            // Exclude: known array-returning methods  ->getAlternatives() etc.
            // Exclude: array-element access  $var['key'] or $var[$idx]
            // Exclude: known array variable names
            if (preg_match('/\bassert(?:Not)?Contains\s*\(\s*[\'"]/', $line)
                && !preg_match('/\bassert(?:Not)?Contains\s*\([^,]+,\s*(?:\[|array\s*\()/', $line)
                && !preg_match('/->(?:' . $arrayMethods . ')\s*\(/', $line)
                && !preg_match('/\$\w+\[/', $line)
                && !preg_match($arrayVarPattern, $line)
            ) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line), 'case' => 'A'];
                continue;
            }
            // Case B: assertStringContainsString/assertStringNotContainsString with array-returning method
            if (preg_match('/\bassertString(?:Not)?ContainsString\s*\(/', $line)
                && preg_match('/->(?:' . $arrayMethods . ')\s*\(/', $line)
            ) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line), 'case' => 'B'];
            }
        }
        return $issues;
    }

    /**
     * Fixes assertContains ↔ assertStringContainsString mismatches.
     *   Case A: assertContains('str', $nonArray)    → assertStringContainsString
     *   Case B: assertStringContainsString($x, $arrayMethod()) → assertContains
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyAssertContainsStringFix(string $content): array
    {
        $arrayMethods = 'getAlternatives|getNames|getCountries|getLanguages|getLocales'
            . '|getCommands|getOptions|getArguments|getBundles|getKeys|getValues'
            . '|getTags|getExtensions|getPlugins|getFiles|getPaths|getItems|getList'
            . '|getResults|getErrors|getWarnings|getGroups|getRoles|getPermissions'
            . '|getParameter|getUsages|getPrefixes|getCurrencies|getGroups|getTemplates'
            . '|getMetadata|getClassNames|getMessages|getProperties|getMethods'
            . '|getConstraints|getAttributes|getHierarchy|getResourcesByType'
            . '|getResources|getNamespaces|getClasses|getDecoratedService|getBindings';
        $arrayVarPattern = '/\$(?:countries|languages|locales|currencies|groups|resources'
            . '|prefixes|paths|files|items|keys|values|tags|options|choices|errors'
            . '|warnings|messages|results|classes|names|roles|bundles|extensions'
            . '|plugins|commands|arguments|attributes|constraints|properties|methods'
            . '|countryCodes|classCodes|usages|templates|namespaces|classNames'
            . '|decoratedServices|bindings)\b/';

        $lines = explode("\n", $content);
        $count = 0;
        foreach ($lines as &$line) {
            // Case A
            if (preg_match('/\bassert(?:Not)?Contains\s*\(\s*[\'"]/', $line)
                && !preg_match('/\bassert(?:Not)?Contains\s*\([^,]+,\s*(?:\[|array\s*\()/', $line)
                && !preg_match('/->(?:' . $arrayMethods . ')\s*\(/', $line)
                && !preg_match('/\$\w+\[/', $line)
                && !preg_match($arrayVarPattern, $line)
            ) {
                $new = preg_replace('/\bassertContains\s*\(/', 'assertStringContainsString(', $line, -1, $c1);
                $new = preg_replace('/\bassertNotContains\s*\(/', 'assertStringNotContainsString(', $new, -1, $c2);
                if ($new !== $line) {
                    $line = $new;
                    $count += (int)$c1 + (int)$c2;
                    continue;
                }
            }
            // Case B
            if (preg_match('/\bassertString(?:Not)?ContainsString\s*\(/', $line)
                && preg_match('/->(?:' . $arrayMethods . ')\s*\(/', $line)
            ) {
                $new = preg_replace('/\bassertStringContainsString\s*\(/', 'assertContains(', $line, -1, $c1);
                $new = preg_replace('/\bassertStringNotContainsString\s*\(/', 'assertNotContains(', $new, -1, $c2);
                if ($new !== $line) {
                    $line = $new;
                    $count += (int)$c1 + (int)$c2;
                }
            }
        }
        return ['fixed' => implode("\n", $lines), 'count' => $count];
    }

    // ── assertAttributeSame → ReflectionProperty ──────────────────────────────

    /**
     * Scans for assertAttributeSame() removed in PHPUnit 10.
     */
    public static function scanAssertAttributeSame(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\bassertAttributeSame\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces $this->assertAttributeSame($expected, 'prop', $obj) with
     * $this->assertSame($expected, (new \ReflectionProperty($obj, 'prop'))->getValue($obj)).
     * Only handles single-line calls with a string-literal property name.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyAssertAttributeSameFix(string $content): array
    {
        $fixed = preg_replace(
            '/\$this->assertAttributeSame\(([^,]+),\s*\'([^\']+)\',\s*(\$\w+)\s*\)/',
            '$this->assertSame($1, (new \\\\ReflectionProperty($3, \'$2\'))->getValue($3))',
            $content,
            -1,
            $count
        );
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── assertObjectHasAttribute → property_exists ───────────────────────────

    /**
     * Scans for assertObjectHasAttribute() removed in PHPUnit 10.
     */
    public static function scanAssertObjectHasAttribute(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\bassertObjectHasAttribute\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces $this->assertObjectHasAttribute('prop', $obj) with
     * $this->assertTrue(property_exists($obj, 'prop')).
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyAssertObjectHasAttributeFix(string $content): array
    {
        $fixed = preg_replace(
            '/\$this->assertObjectHasAttribute\(\s*\'([^\']+)\',\s*(\$\w+)\s*\)/',
            '$this->assertTrue(property_exists($2, \'$1\'))',
            $content,
            -1,
            $count
        );
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── assertInternalType → assertIsX ───────────────────────────────────────

    /**
     * Scans for assertInternalType() removed in PHPUnit 10.
     */
    public static function scanAssertInternalType(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\bassertInternalType\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces assertInternalType('type', $val) with the appropriate assertIsX($val) method.
     * Supports: array, string, int/integer, float/double, bool/boolean, object,
     *           callable, numeric, null, resource.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyAssertInternalTypeFix(string $content): array
    {
        $map = [
            'array'    => 'assertIsArray',
            'string'   => 'assertIsString',
            'int'      => 'assertIsInt',
            'integer'  => 'assertIsInt',
            'float'    => 'assertIsFloat',
            'double'   => 'assertIsFloat',
            'bool'     => 'assertIsBool',
            'boolean'  => 'assertIsBool',
            'object'   => 'assertIsObject',
            'callable' => 'assertIsCallable',
            'numeric'  => 'assertIsNumeric',
            'null'     => 'assertNull',
            'resource' => 'assertIsResource',
        ];
        $total = 0;
        foreach ($map as $type => $method) {
            $content = preg_replace(
                '/\bassertInternalType\(\s*[\'"]' . preg_quote($type, '/') . '[\'"]\s*,\s*/',
                $method . '(',
                $content,
                -1,
                $c
            ) ?? $content;
            $total += (int)$c;
        }
        return ['fixed' => $content, 'count' => $total];
    }

    // ── getMockClass → get_class(createMock()) ───────────────────────────────

    /**
     * Scans for getMockClass() removed in PHPUnit 10.
     */
    public static function scanGetMockClass(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\bgetMockClass\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces $this->getMockClass('ClassName') with get_class($this->createMock('ClassName')).
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyGetMockClassFix(string $content): array
    {
        // Replace $this->getMockClass('X') → get_class($this->createMock('X'))
        $fixed = preg_replace(
            '/\$this->getMockClass\(([^)]+)\)/',
            'get_class($this->createMock($1))',
            $content,
            -1,
            $count
        );
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── $this->getName() → $this->name() ─────────────────────────────────────

    /**
     * Scans for TestCase::getName() removed in PHPUnit 10 (replaced by name()).
     */
    public static function scanGetNameTestCase(string $content): array
    {
        // Only apply in PHPUnit TestCase subclasses — $this->getName() is a
        // TestCase method; in production classes it refers to unrelated methods.
        if (!preg_match('/\bextends\b.*\bTestCase\b|\bextends\b.*Test\b/i', $content)) {
            return [];
        }
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (preg_match('/\$this->getName\s*\(\s*\)/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /**
     * Replaces $this->getName() with $this->name() (PHPUnit 10+ API).
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyGetNameTestCaseFix(string $content): array
    {
        $fixed = preg_replace('/\$this->getName\s*\(\s*\)/', '$this->name()', $content, -1, $count);
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ── static provider with $this body → remove static ──────────────────────

    /**
     * Returns the set of method names that are referenced by @dataProvider or
     * #[DataProvider(...)] annotations *within the same file*.
     * Only these methods are safe to de-staticize — we must never touch a method
     * that is declared static in a parent and merely overridden here.
     */
    private static function localDataProviderNames(string $content): array
    {
        $names = [];
        // @dataProvider methodName
        preg_match_all('/@dataProvider\s+(\w+)/', $content, $m);
        foreach ($m[1] as $n) {
            $names[$n] = true;
        }
        // #[DataProvider('methodName')] or #[DataProvider("methodName")]
        preg_match_all('/#\[DataProvider\s*\(\s*[\'"](\w+)[\'"]\s*\)\]/', $content, $m);
        foreach ($m[1] as $n) {
            $names[$n] = true;
        }
        return $names;
    }

    /**
     * Scans for static data-provider methods (referenced by @dataProvider in the
     * SAME file) whose body contains $this->. These cannot be truly static because
     * PHPUnit calls them in object context when $this is used.
     *
     * IMPORTANT: we only flag methods whose name appears in a local @dataProvider
     * annotation. This prevents incorrectly flagging child-class overrides of
     * parent static methods (which would cause a PHP fatal error if de-staticized).
     */
    public static function scanStaticProviderWithThis(string $content): array
    {
        $localProviders = self::localDataProviderNames($content);
        if (empty($localProviders)) {
            return [];
        }

        $issues = [];
        $lines  = explode("\n", $content);
        $total  = count($lines);
        for ($i = 0; $i < $total; $i++) {
            $line = $lines[$i];
            if (!preg_match(
                '/^\s*(?:public|protected|private)\s+static\s+function\s+(\w+)\s*\(/',
                $line, $m
            )) {
                continue;
            }
            // Only process methods that are local @dataProvider targets
            if (!isset($localProviders[$m[1]])) {
                continue;
            }
            // Collect method body up to matching closing brace
            $depth = 0;
            $body  = '';
            $j     = $i;
            while ($j < $total) {
                $body  .= $lines[$j];
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth <= 0 && $j > $i) {
                    break;
                }
                ++$j;
            }
            if (str_contains($body, '$this->')) {
                $issues[] = [
                    'line'    => $i + 1,
                    'snippet' => rtrim($line),
                    'method'  => $m[1],
                ];
            }
        }
        return $issues;
    }

    /**
     * Removes the `static` keyword from data-provider methods (those referenced
     * by @dataProvider in the same file) whose body references $this->.
     *
     * Safe: only touches methods listed as @dataProvider in this file, never
     * child-class overrides of parent static methods.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyStaticProviderWithThisFix(string $content): array
    {
        $localProviders = self::localDataProviderNames($content);
        if (empty($localProviders)) {
            return ['fixed' => $content, 'count' => 0];
        }

        $lines = explode("\n", $content);
        $total = count($lines);
        $count = 0;
        for ($i = 0; $i < $total; $i++) {
            $line = $lines[$i];
            if (!preg_match(
                '/^\s*(?:public|protected|private)\s+static\s+function\s+(\w+)\s*\(/',
                $line, $m
            )) {
                continue;
            }
            // Only process local @dataProvider targets
            if (!isset($localProviders[$m[1]])) {
                continue;
            }
            $depth = 0;
            $body  = '';
            $j     = $i;
            while ($j < $total) {
                $body  .= $lines[$j];
                $depth += substr_count($lines[$j], '{') - substr_count($lines[$j], '}');
                if ($depth <= 0 && $j > $i) {
                    break;
                }
                ++$j;
            }
            if (str_contains($body, '$this->')) {
                // Remove the first occurrence of 'static ' in this declaration line
                $lines[$i] = preg_replace('/\bstatic\s+/', '', $line, 1);
                ++$count;
            }
        }
        return ['fixed' => implode("\n", $lines), 'count' => $count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanLibxmlEntityLoader / applyLibxmlEntityLoaderFix
    // libxml_disable_entity_loader() is deprecated since PHP 8.0 and its calls
    // corrupt global libxml state across test runs.  Remove all calls.
    // ─────────────────────────────────────────────────────────────────────────

    public static function scanLibxmlEntityLoader(string $content): array
    {
        $issues = [];
        if (!str_contains($content, 'libxml_disable_entity_loader')) {
            return $issues;
        }
        foreach (explode("\n", $content) as $lineNo => $line) {
            if (str_contains($line, 'libxml_disable_entity_loader')) {
                $issues[] = [
                    'line'    => $lineNo + 1,
                    'message' => 'libxml_disable_entity_loader() is deprecated since PHP 8.0',
                    'context' => trim($line),
                ];
            }
        }
        return $issues;
    }

    public static function applyLibxmlEntityLoaderFix(string $content): array
    {
        if (!str_contains($content, 'libxml_disable_entity_loader')) {
            return ['fixed' => $content, 'count' => 0];
        }

        $lines   = explode("\n", $content);
        $count   = 0;
        $removed = [];

        // First pass: collect variable names assigned from libxml_disable_entity_loader
        $varNames = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/^\s*(\$\w+)\s*=\s*libxml_disable_entity_loader\s*\(/', $line, $m)) {
                $varNames[] = preg_quote($m[1], '/');
                $removed[$i] = true;
                ++$count;
            } elseif (preg_match('/^\s*libxml_disable_entity_loader\s*\(/', $line)) {
                $removed[$i] = true;
                ++$count;
            }
        }

        // Second pass: remove orphaned restore calls that use only those variables
        if (!empty($varNames)) {
            $varPattern = implode('|', $varNames);
            foreach ($lines as $i => $line) {
                if (!isset($removed[$i]) && preg_match(
                    '/^\s*libxml_disable_entity_loader\s*\(\s*(?:' . $varPattern . ')\s*\)\s*;/',
                    $line
                )) {
                    $removed[$i] = true;
                    ++$count;
                }
            }
        }

        $result = implode("\n", array_filter($lines, fn($i) => !isset($removed[$i]), ARRAY_FILTER_USE_KEY));
        return ['fixed' => $result, 'count' => $count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanSplAttach / applySplAttachFix
    // SplObjectStorage::attach($obj) → SplObjectStorage::offsetSet($obj, null)
    // ─────────────────────────────────────────────────────────────────────────

    public static function scanSplAttach(string $content): array
    {
        $issues = [];
        if (!str_contains($content, '->attach(') || !str_contains($content, 'SplObjectStorage')) {
            return $issues;
        }
        foreach (explode("\n", $content) as $lineNo => $line) {
            if (str_contains($line, '->attach(')) {
                $issues[] = [
                    'line'    => $lineNo + 1,
                    'message' => 'SplObjectStorage::attach() deprecated: use offsetSet()',
                    'context' => trim($line),
                ];
            }
        }
        return $issues;
    }

    public static function applySplAttachFix(string $content): array
    {
        if (!str_contains($content, '->attach(') || !str_contains($content, 'SplObjectStorage')) {
            return ['fixed' => $content, 'count' => 0];
        }
        $count = 0;
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
            if (!str_contains($line, '->attach(')) {
                continue;
            }
            $args = self::parseTopLevelArgs($line, 'attach');
            if ($args === null || empty($args)) {
                continue;
            }
            $indent = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
            // Extract the LHS (everything before ->attach)
            $lhsMatch = preg_match('/(.*)->attach\s*\(/', $line, $lhsM);
            $lhs = $lhsMatch ? rtrim($lhsM[1]) : '';
            if (isset($args[1])) {
                // ->attach($obj, $data) → ->offsetSet($obj, $data)
                $line = $indent . ltrim($lhs) . '->offsetSet(' . $args[0] . ', ' . $args[1] . ');';
            } else {
                // ->attach($obj) → ->offsetSet($obj, null)
                $line = $indent . ltrim($lhs) . '->offsetSet(' . $args[0] . ', null);';
            }
            ++$count;
        }
        return ['fixed' => implode("\n", $lines), 'count' => $count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanSplDetach / applySplDetachFix
    // SplObjectStorage::detach($obj) → SplObjectStorage::offsetUnset($obj)
    // ─────────────────────────────────────────────────────────────────────────

    public static function scanSplDetach(string $content): array
    {
        $issues = [];
        if (!str_contains($content, '->detach(') || !str_contains($content, 'SplObjectStorage')) {
            return $issues;
        }
        foreach (explode("\n", $content) as $lineNo => $line) {
            if (str_contains($line, '->detach(')) {
                $issues[] = [
                    'line'    => $lineNo + 1,
                    'message' => 'SplObjectStorage::detach() deprecated: use offsetUnset()',
                    'context' => trim($line),
                ];
            }
        }
        return $issues;
    }

    public static function applySplDetachFix(string $content): array
    {
        if (!str_contains($content, '->detach(') || !str_contains($content, 'SplObjectStorage')) {
            return ['fixed' => $content, 'count' => 0];
        }
        $fixed = preg_replace('/(\$\w+)->detach\(/', '$1->offsetUnset(', $content, -1, $count);
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanEStrict / applyEStrictFix
    // E_STRICT was removed in PHP 8.4 — replace with 0
    // ─────────────────────────────────────────────────────────────────────────

    public static function scanEStrict(string $content): array
    {
        $issues = [];
        if (!preg_match('/\bE_STRICT\b/', $content)) {
            return $issues;
        }
        foreach (explode("\n", $content) as $lineNo => $line) {
            if (preg_match('/\bE_STRICT\b/', $line)) {
                $issues[] = [
                    'line'    => $lineNo + 1,
                    'message' => 'E_STRICT was removed in PHP 8.4, use 0',
                    'context' => trim($line),
                ];
            }
        }
        return $issues;
    }

    public static function applyEStrictFix(string $content): array
    {
        if (!preg_match('/\bE_STRICT\b/', $content)) {
            return ['fixed' => $content, 'count' => 0];
        }
        $fixed = preg_replace('/\bE_STRICT\b/', '0', $content, -1, $count);
        return ['fixed' => $fixed ?? $content, 'count' => (int)$count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanEscapedQuoteAssert / applyEscapedQuoteAssertFix
    // In PHP 8, exception messages no longer escape double-quotes.
    // Tests that use \" inside expectExceptionMessage / assertContains / etc.
    // need to have those backslashes removed.
    // ─────────────────────────────────────────────────────────────────────────

    public static function scanEscapedQuoteAssert(string $content): array
    {
        $issues = [];
        // Only flag single-quoted strings: '...\"...' — in single-quoted PHP strings,
        // \" is literal backslash+quote; double-quoted \" is already the correct PHP escape.
        $assertPattern = "/(?:expectExceptionMessage|assertContains|assertStringContains"
            . "|assertStringContainsString|assertExceptionMessage)\s*\(\s*'[^']*\\\\\"[^']*'/";
        if (!preg_match($assertPattern, $content)) {
            return $issues;
        }
        foreach (explode("\n", $content) as $lineNo => $line) {
            if (preg_match($assertPattern, $line)) {
                $issues[] = [
                    'line'    => $lineNo + 1,
                    'message' => 'Assertion string contains \\\" — PHP 8 exception messages use unescaped "',
                    'context' => trim($line),
                ];
            }
        }
        return $issues;
    }

    public static function applyEscapedQuoteAssertFix(string $content): array
    {
        $assertMethods = 'expectExceptionMessage|assertContains|assertStringContains'
            . '|assertStringContainsString|assertExceptionMessage';

        if (!preg_match('/(?:' . $assertMethods . ')/', $content) || !str_contains($content, '\\"')) {
            return ['fixed' => $content, 'count' => 0];
        }

        // Use a callback to fix only the string argument of assertion calls
        $count = 0;
        $fixed = preg_replace_callback(
            '/\b(?:' . $assertMethods . ')\s*\(\s*([\'"])((?:[^\\\\]|\\\\.)*)([\'"])/',
            function (array $m) use (&$count): string {
                $quote    = $m[1];
                $body     = $m[2];
                $endQuote = $m[3];
                if ($quote !== $endQuote) {
                    return $m[0]; // mismatched quotes — leave alone
                }
                // Single-quoted: \" inside → remove backslash
                if ($quote === "'" && str_contains($body, '\\"')) {
                    $newBody = str_replace('\\"', '"', $body);
                    ++$count;
                    return str_replace($body, $newBody, $m[0]);
                }
                // Double-quoted: \" is already the correct PHP escape for " — leave alone
                return $m[0];
            },
            $content
        );
        return ['fixed' => $fixed ?? $content, 'count' => $count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanAssertContainsRevert / applyAssertContainsRevertFix
    // Reverts wrongly-converted assertStringContainsString(needle, arrayExpr)
    // back to assertContains(needle, arrayExpr) when the haystack is clearly
    // array-returning (known methods or array-element access).
    // ─────────────────────────────────────────────────────────────────────────

    /** Methods known to return arrays/iterables, not strings. */
    private static function arrayReturnMethods(): string
    {
        return 'getParameter|getUsages|getCountries|getLanguages|getLocales|getCurrencies'
            . '|getPrefixes|getGroups|getTemplates|getMetadata|getClassNames|getMessages'
            . '|getProperties|getMethods|getConstraints|getPaths|getValues|getAttributes'
            . '|getHierarchy|getResourcesByType|getResources|getNamespaces|getClasses'
            . '|getDecoratedService|getTags|getArguments|getBindings';
    }

    public static function scanAssertContainsRevert(string $content): array
    {
        $issues = [];
        $arrayMethods = self::arrayReturnMethods();
        foreach (explode("\n", $content) as $idx => $line) {
            if (!str_contains($line, 'assertStringContainsString')) {
                continue;
            }
            // haystack is an array-returning method call
            if (preg_match('/->(?:' . $arrayMethods . ')\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
                continue;
            }
            // haystack is bare array-element access (NOT followed by method call)
            // e.g. $prefixes['Foo'] — but NOT $items[0]->getUri() which returns a string
            if (preg_match('/assertStringContainsString\([^,]+,\s*\$\w+\[[^\]]+\]\s*\)/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /** @return array{fixed: string, count: int} */
    public static function applyAssertContainsRevertFix(string $content): array
    {
        $arrayMethods = self::arrayReturnMethods();
        $count = 0;
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
            if (!str_contains($line, 'assertStringContainsString')) {
                continue;
            }
            $args = self::parseTopLevelArgs($line, 'assertStringContainsString');
            if ($args === null || !isset($args[0], $args[1])) {
                continue;
            }
            $haystack = $args[1];
            $isArrayHaystack = false;
            // haystack is array-returning method call
            if (preg_match('/->(?:' . $arrayMethods . ')\s*\(/', $haystack)) {
                $isArrayHaystack = true;
            }
            // haystack is bare array-element access (NOT followed by -> method call)
            if (!$isArrayHaystack && preg_match('/^\$\w+\[[^\]]+\]\s*$/', $haystack)) {
                $isArrayHaystack = true;
            }
            if (!$isArrayHaystack) {
                continue;
            }
            $newArgs = implode(', ', $args);
            $prefix  = preg_match('/(\$(?:this->|self::))/', $line, $m) ? $m[1] : '$this->';
            $indent  = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
            $line    = $indent . $prefix . 'assertContains(' . $newArgs . ');';
            ++$count;
        }
        return ['fixed' => implode("\n", $lines), 'count' => $count];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanAssertEqualsWithDelta / applyAssertEqualsWithDeltaFix
    // assertEquals(x, y, null, DELTA) → assertEqualsWithDelta(x, y, DELTA)
    // assertEquals(x, y, '', DELTA)   → assertEqualsWithDelta(x, y, DELTA)
    // Also fixes assertEquals(x, y, null) → assertEquals(x, y, '') when
    // null is the LAST arg (already in assertNullMsg but catches multi-line).
    // ─────────────────────────────────────────────────────────────────────────

    public static function scanAssertEqualsWithDelta(string $content): array
    {
        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            if (!preg_match('/\$(?:this->|self::)assertEquals\s*\(/', $line)) {
                continue;
            }
            $args = self::parseTopLevelArgs($line, 'assertEquals');
            if ($args !== null && isset($args[2], $args[3])
                && ($args[2] === 'null' || $args[2] === "''")
            ) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /** @return array{fixed: string, count: int} */
    public static function applyAssertEqualsWithDeltaFix(string $content): array
    {
        $count = 0;
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
            if (!preg_match('/\$(?:this->|self::)assertEquals\s*\(/', $line)) {
                continue;
            }
            $args = self::parseTopLevelArgs($line, 'assertEquals');
            if ($args === null || !isset($args[2], $args[3])) {
                continue;
            }
            if ($args[2] !== 'null' && $args[2] !== "''") {
                continue;
            }
            // Build replacement: assertEqualsWithDelta($args[0], $args[1], $args[3])
            $indent = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
            $prefix = preg_match('/(\$(?:this->|self::))/', $line, $m) ? $m[1] : '$this->';
            $line = $indent . $prefix . 'assertEqualsWithDelta('
                . $args[0] . ', ' . $args[1] . ', ' . $args[3] . ');';
            ++$count;
        }
        return ['fixed' => implode("\n", $lines), 'count' => $count];
    }

    /**
     * Parse top-level comma-separated arguments of a named method call on a single line.
     * Properly handles nested parentheses, brackets, and quoted strings.
     * Returns null if the method call is not found or spans multiple lines.
     *
     * @return string[]|null
     */
    private static function parseTopLevelArgs(string $line, string $methodName): ?array
    {
        $search = $methodName . '(';
        $pos    = strpos($line, $search);
        if ($pos === false) {
            return null;
        }
        $pos += strlen($search);
        $len   = strlen($line);
        $depth = 1;
        $args  = [];
        $start = $pos;
        $inStr = false;
        $quote = '';

        for ($i = $pos; $i < $len; $i++) {
            $c = $line[$i];
            if ($inStr) {
                if ($c === '\\') { $i++; continue; }
                if ($c === $quote) { $inStr = false; }
                continue;
            }
            if ($c === '"' || $c === "'") { $inStr = true; $quote = $c; continue; }
            if ($c === '(' || $c === '[') { $depth++; continue; }
            if ($c === ')' || $c === ']') {
                $depth--;
                if ($depth === 0) {
                    $args[] = trim(substr($line, $start, $i - $start));
                    break;
                }
                continue;
            }
            if ($c === ',' && $depth === 1) {
                $args[] = trim(substr($line, $start, $i - $start));
                $start  = $i + 1;
            }
        }

        return $args ?: null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // scanAssertContainsString4 / applyAssertContainsString4Fix
    // assertContains(needle, stringExpr) → assertStringContainsString
    // For cases where haystack is clearly a string: ->getMessage(), ->getOutput(),
    // ->getDisplay(), ->getCommandLine(), ->getContent(), ->render(), etc.
    // ─────────────────────────────────────────────────────────────────────────

    private static function stringReturnMethods(): string
    {
        return 'getMessage|getOutput|getDisplay|getCommandLine|getContent|render'
            . '|getBody|getText|getString|getDescription|dump|getLine|toString'
            . '|getSql|getQuery|getErrorOutput|getCommandLine|__toString';
    }

    public static function scanAssertContainsString4(string $content): array
    {
        $issues = [];
        $strMethods = self::stringReturnMethods();
        foreach (explode("\n", $content) as $idx => $line) {
            if (!str_contains($line, 'assertContains(')) {
                continue;
            }
            // haystack is a string-returning method
            if (preg_match('/assertContains\s*\([^,]+,\s*[^)]+->(?:' . $strMethods . ')\s*\(/', $line)) {
                $issues[] = ['line' => $idx + 1, 'snippet' => rtrim($line)];
            }
        }
        return $issues;
    }

    /** @return array{fixed: string, count: int} */
    public static function applyAssertContainsString4Fix(string $content): array
    {
        $strMethods = self::stringReturnMethods();
        $count = 0;
        $lines = explode("\n", $content);
        foreach ($lines as &$line) {
            if (!str_contains($line, 'assertContains(')) {
                continue;
            }
            // Verify haystack (arg[1]) contains a string-returning method call
            if (!preg_match('/->(?:' . $strMethods . ')\s*\(/', $line)) {
                continue;
            }
            $args = self::parseTopLevelArgs($line, 'assertContains');
            if ($args === null || !isset($args[0], $args[1])) {
                continue;
            }
            // Rebuild args and replace assertContains with assertStringContainsString
            $newArgs = implode(', ', $args);
            $prefix  = preg_match('/(\$(?:this->|self::))/', $line, $m) ? $m[1] : '$this->';
            $indent  = str_repeat(' ', strlen($line) - strlen(ltrim($line)));
            $line    = $indent . $prefix . 'assertStringContainsString(' . $newArgs . ');';
            ++$count;
        }
        return ['fixed' => implode("\n", $lines), 'count' => $count];
    }

    // ── Generic file-scanning helpers ────────────────────────────────────────

    protected function scanFiles(\Iterator $files, callable $scanner, string $baseDir): array
    {
        $results = [];

        foreach ($files as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $content = @file_get_contents($fileInfo->getPathname());
            if ($content === false) {
                continue; // unreadable — skip silently
            }

            $issues = $scanner($content);
            if (!empty($issues)) {
                $rel           = $this->relativePath($fileInfo->getPathname(), $baseDir);
                $results[$rel] = $issues;
            }
        }

        return $results;
    }

    /**
     * Counts all issues across all files from a scanFiles() result.
     */
    protected function countIssues(array $scanResults): int
    {
        return array_sum(array_map('count', $scanResults));
    }

    /**
     * Counts unique files that have at least one issue.
     */
    protected function countFiles(array $scanResults): int
    {
        return count($scanResults);
    }
}
