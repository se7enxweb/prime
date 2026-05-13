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

    // ── PHPUnit 11 compatibility scanner ──────────────────────────────────────

    /**
     * Scans PHP test source for data-provider methods that are not declared static.
     *
     * PHPUnit 11 requires every method referenced by @dataProvider or
     * #[DataProvider(…)] to be declared `public static`.
     * Non-static providers still execute in PHPUnit 10 but produce a deprecation;
     * in PHPUnit 11 they are treated as errors.
     *
     * Detects:
     *   @dataProvider methodName        ← docblock annotation (any indentation)
     *   #[DataProvider('methodName')]   ← PHP 8 attribute
     *
     * For each referenced provider name it then searches the same file content for
     * a non-static `public function methodName(` declaration.
     *
     * Returns [ 'line' => int, 'snippet' => string, 'method' => string ]
     */
    public static function scanPhpunit(string $content): array
    {
        $issues = [];
        $lines  = explode("\n", $content);

        // ── Collect all data-provider method names referenced in this file ─────
        $providers = [];
        foreach ($lines as $line) {
            // @dataProvider methodName  (docblock)
            if (preg_match('/@dataProvider\s+(\w+)/', $line, $m)) {
                $providers[$m[1]] = true;
            }
            // #[DataProvider('methodName')]  (PHP 8 attribute)
            if (preg_match('/#\[(?:\w+\\\\)*DataProvider\s*\(\s*[\'"](\w+)[\'"]\s*\)\s*]/', $line, $m)) {
                $providers[$m[1]] = true;
            }
        }

        if (empty($providers)) {
            return [];
        }

        // ── Find non-static declarations of those provider methods ────────────
        foreach ($lines as $idx => $line) {
            // Match `public function name(` without `static` on the same line.
            // The negative lookahead ensures `static` is not already present
            // somewhere on the line before the `function` keyword.
            if (preg_match('/\bpublic\b(?!\s+static)\s+function\s+(\w+)\s*\(/i', $line, $m)) {
                $methodName = $m[1];
                if (isset($providers[$methodName])) {
                    $issues[] = [
                        'line'    => $idx + 1,
                        'snippet' => rtrim($line),
                        'method'  => $methodName,
                    ];
                }
            }
        }

        return $issues;
    }

    /**
     * Applies the PHPUnit data-provider fix to a string of PHP source.
     *
     * Inserts `static` into every `public function methodName(` declaration
     * where methodName is referenced by @dataProvider or #[DataProvider(…)].
     *
     * This method is PURE — it returns the fixed string, never writes a file.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyPhpunitFix(string $content): array
    {
        $lines = explode("\n", $content);

        // Collect provider names (same logic as scanPhpunit)
        $providers = [];
        foreach ($lines as $line) {
            if (preg_match('/@dataProvider\s+(\w+)/', $line, $m)) {
                $providers[$m[1]] = true;
            }
            if (preg_match('/#\[(?:\w+\\\\)*DataProvider\s*\(\s*[\'"](\w+)[\'"]\s*\)\s*]/', $line, $m)) {
                $providers[$m[1]] = true;
            }
        }

        if (empty($providers)) {
            return ['fixed' => $content, 'count' => 0];
        }

        $count = 0;

        // Replace `public function name(` → `public static function name(`
        // only for methods whose name is in the providers set.
        $fixed = preg_replace_callback(
            '/\b(public)\b((?!\s+static)\s+)function\s+(\w+)\s*\(/i',
            static function (array $m) use ($providers, &$count): string {
                $methodName = $m[3];
                if (isset($providers[$methodName])) {
                    ++$count;
                    // Preserve original whitespace between public and function.
                    return $m[1] . $m[2] . 'static function ' . $methodName . '(';
                }
                return $m[0];
            },
            $content
        );

        return ['fixed' => $fixed ?? $content, 'count' => $count];
    }

    // ── Return-type compatibility scanner ─────────────────────────────────────

    /**
     * Map of well-known PHP built-in interface / parent-class short names
     * to the methods they require and the PHP 8+ return type for each.
     *
     * Used by scanReturnTypeCompat() and applyReturnTypeCompatFix().
     *
     * @return array<string, array<string, string>>
     */
    private static function interfaceReturnTypeMap(): array
    {
        return [
            // PHP core interfaces
            'Countable'               => ['count'         => 'int'],
            'Stringable'              => ['__toString'    => 'string'],
            'IteratorAggregate'       => ['getIterator'   => '\\Traversable'],
            'Iterator'                => [
                'current' => 'mixed', 'key' => 'mixed',
                'next'    => 'void',  'rewind' => 'void', 'valid' => 'bool',
            ],
            'ArrayAccess'             => [
                'offsetExists' => 'bool',  'offsetGet'    => 'mixed',
                'offsetSet'    => 'void',  'offsetUnset'  => 'void',
            ],
            'JsonSerializable'        => ['jsonSerialize' => 'mixed'],
            // Session storage
            'SessionHandlerInterface' => [
                'open'    => 'bool',   'close'   => 'bool',
                'read'    => 'string|false', 'write' => 'bool',
                'destroy' => 'bool',   'gc'      => 'int|false',
            ],
            // PDO subclasses
            'PDO'                     => [
                'beginTransaction' => 'bool',
                'rollBack'         => 'bool',
                'getAttribute'     => 'mixed',
                'prepare'          => '\\PDOStatement|false',
            ],
        ];
    }

    /**
     * Collects the set of (interface/parent short name → method → returnType)
     * entries that apply to the class declared in $content.
     *
     * Returns [] when the class does not implement any of the known interfaces.
     *
     * @return array<string, string>  methodName → expectedReturnType
     */
    private static function resolveMethodReturnTypes(string $content): array
    {
        $map = self::interfaceReturnTypeMap();
        $applicable = [];

        foreach (explode("\n", $content) as $line) {
            // Matches: class Foo [extends Bar] [implements A, B, C] [{|EOL]
            if (!preg_match('/\bclass\s+\w+(?:\s+extends\s+(\w+))?(?:\s+implements\s+(.+?))?(?:\s*\{|$)/', $line, $m)) {
                continue;
            }

            $declaredNames = [];

            // extends ClassName → treat as if implements for PDO etc.
            if (!empty($m[1])) {
                $declaredNames[] = trim($m[1]);
            }

            // implements A, B\C, \D\E  → use the short (last) name
            if (!empty($m[2])) {
                foreach (preg_split('/\s*,\s*/', $m[2]) as $fqcn) {
                    $parts = explode('\\', trim($fqcn));
                    $declaredNames[] = end($parts);
                }
            }

            foreach ($declaredNames as $name) {
                if (isset($map[$name])) {
                    foreach ($map[$name] as $method => $type) {
                        $applicable[$method] = $type;
                    }
                }
            }

            break; // only care about the first class declaration
        }

        return $applicable;
    }

    /**
     * Scans PHP source for methods that are missing required return type
     * declarations because they implement a well-known PHP interface.
     *
     * Detects methods like `public function count()` on a class implementing
     * Countable that lack the `: int` return type required by PHP 8.1+.
     *
     * Returns [ 'line' => int, 'snippet' => string, 'method' => string,
     *            'expectedType' => string ]
     */
    public static function scanReturnTypeCompat(string $content): array
    {
        $applicable = self::resolveMethodReturnTypes($content);

        if (empty($applicable)) {
            return [];
        }

        $issues = [];
        foreach (explode("\n", $content) as $idx => $line) {
            // Match `public [static] function name(...)` with NO colon-return-type
            // Single-line signatures only (multi-line signatures are uncommon for built-in methods).
            if (!preg_match('/\bpublic\b.*\bfunction\s+(\w+)\s*\([^)]*\)\s*(?:\{|;|$)/i', $line, $m)) {
                continue;
            }
            if (preg_match('/\)\s*:\s*\S/', $line)) {
                continue; // already has a return type
            }

            $methodName = $m[1];
            if (isset($applicable[$methodName])) {
                $issues[] = [
                    'line'         => $idx + 1,
                    'snippet'      => rtrim($line),
                    'method'       => $methodName,
                    'expectedType' => $applicable[$methodName],
                ];
            }
        }

        return $issues;
    }

    /**
     * Adds missing return type declarations to methods identified by
     * scanReturnTypeCompat().
     *
     * This method is PURE — it returns the fixed string, never writes a file.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applyReturnTypeCompatFix(string $content): array
    {
        $applicable = self::resolveMethodReturnTypes($content);

        if (empty($applicable)) {
            return ['fixed' => $content, 'count' => 0];
        }

        $count = 0;
        $lines = explode("\n", $content);
        $result = [];

        foreach ($lines as $line) {
            if (
                preg_match('/(\bpublic\b.*\bfunction\s+(\w+)\s*\([^)]*\))(\s*)(\{|;|$)/i', $line, $m) &&
                !preg_match('/\)\s*:\s*\S/', $line)
            ) {
                $methodName = $m[2];
                if (isset($applicable[$methodName])) {
                    $returnType = $applicable[$methodName];
                    // Insert ': ReturnType' between the closing ')' and the '{' or ';'
                    $line = preg_replace(
                        '/(\bpublic\b.*\bfunction\s+' . preg_quote($methodName, '/') . '\s*\([^)]*\))(\s*)(\{|;|$)/i',
                        '$1: ' . $returnType . '$2$3',
                        $line
                    );
                    ++$count;
                }
            }

            $result[] = $line;
        }

        return ['fixed' => implode("\n", $result), 'count' => $count];
    }

    // ── Serializable interface scanner ────────────────────────────────────────

    /**
     * Scans PHP source for classes that implement the deprecated \Serializable
     * interface without also providing __serialize() / __unserialize() methods.
     *
     * PHP 8.1 deprecated implementing Serializable alone; classes must either
     * drop the interface and use __serialize()/__unserialize() exclusively, or
     * implement both sets of methods.
     *
     * Returns [ 'line' => int, 'snippet' => string, 'class' => string ]
     */
    public static function scanSerializable(string $content): array
    {
        $issues = [];
        $lines  = explode("\n", $content);

        $hasSerialize   = (bool) preg_match('/\bfunction\s+__serialize\s*\(/i', $content);
        $hasUnserialize = (bool) preg_match('/\bfunction\s+__unserialize\s*\(/i', $content);

        if ($hasSerialize && $hasUnserialize) {
            return []; // already migrated
        }

        foreach ($lines as $idx => $line) {
            // Detect: implements ... \Serializable  or  extends ... \Serializable
            if (preg_match('/(?:implements|extends)\s+[^{]*\\\\?Serializable\b/', $line)) {
                if (preg_match('/\bclass\s+(\w+)/', $line, $m)) {
                    $issues[] = [
                        'line'    => $idx + 1,
                        'snippet' => rtrim($line),
                        'class'   => $m[1],
                    ];
                } elseif ($issues === []) {
                    // class name on a previous line – use the line as-is
                    $issues[] = [
                        'line'    => $idx + 1,
                        'snippet' => rtrim($line),
                        'class'   => '(unknown)',
                    ];
                }
            }

            // Also detect `interface Foo extends \Serializable`
            if (preg_match('/\binterface\s+(\w+)\s+extends\s+[^{]*\\\\?Serializable\b/', $line, $m)) {
                $issues[] = [
                    'line'    => $idx + 1,
                    'snippet' => rtrim($line),
                    'class'   => $m[1],
                ];
            }
        }

        return $issues;
    }

    /**
     * Adds __serialize() / __unserialize() bridge methods that delegate to the
     * existing serialize() / unserialize() methods, suppressing the Serializable
     * deprecation while maintaining backward compatibility.
     *
     * Skips files that already have __serialize() / __unserialize(), or that
     * have no serialize() method (unexpected / nothing to bridge).
     *
     * This method is PURE — it returns the fixed string, never writes a file.
     *
     * @return array{fixed: string, count: int}
     */
    public static function applySerializableFix(string $content): array
    {
        // Already migrated
        if (preg_match('/\bfunction\s+__serialize\s*\(/i', $content) &&
            preg_match('/\bfunction\s+__unserialize\s*\(/i', $content)) {
            return ['fixed' => $content, 'count' => 0];
        }

        // Nothing to bridge — no serialize() method to wrap
        if (!preg_match('/\bpublic\s+function\s+serialize\s*\(\s*\)/i', $content)) {
            return ['fixed' => $content, 'count' => 0];
        }

        // Find the closing brace of the serialize() method and insert the bridge after it
        $bridge = <<<'PHP'

    public function __serialize(): array
    {
        return ['serialized' => $this->serialize()];
    }

    public function __unserialize(array $data): void
    {
        $this->unserialize($data['serialized']);
    }
PHP;

        // Insert the bridge immediately before the unserialize() method declaration.
        $fixed = preg_replace(
            '/([ \t]*)(?:\/\*\*[^*]*\*+(?:[^*\/][^*]*\*+)*\/\s*)?' .  // optional docblock
            '(public\s+function\s+unserialize\s*\()/i',
            $bridge . "\n\n" . '$1$2',
            $content,
            1,
            $count
        );

        if ($count === 0 || $fixed === null) {
            return ['fixed' => $content, 'count' => 0];
        }

        return ['fixed' => $fixed, 'count' => 1];
    }

    // ── Optional-before-required parameter scanner ───────────────────────────

    /**
     * Scans PHP source for function/method parameters that have a default value
     * but are followed by one or more required (no-default) parameters.
     *
     * PHP 8.0 deprecated this pattern and PHP 9 will make it an error.
     * Auto-fixing is intentionally NOT provided because the correct remediation
     * is context-dependent (reorder parameters, remove the default value, or
     * change the required parameter to optional).
     *
     * Returns [ 'line' => int, 'snippet' => string, 'param' => string ]
     */
    public static function scanOptionalBeforeRequired(string $content): array
    {
        $issues = [];

        foreach (explode("\n", $content) as $idx => $line) {
            // Match function/method signatures that fit on one line
            if (!preg_match('/\bfunction\s+\w+\s*\(([^)]+)\)/i', $line, $m)) {
                continue;
            }

            $paramList = $m[1];

            // Split by comma (simple; won't handle nested generics/defaults with commas)
            $params    = array_map('trim', explode(',', $paramList));
            $seenOptional = null;

            foreach ($params as $param) {
                if ($param === '' || str_starts_with($param, '...')) {
                    continue; // variadic always last
                }
                $hasDefault = str_contains($param, '=');

                if ($hasDefault) {
                    $seenOptional = $param;
                } elseif ($seenOptional !== null) {
                    // A required param comes AFTER an optional one
                    $issues[] = [
                        'line'    => $idx + 1,
                        'snippet' => rtrim($line),
                        'param'   => trim(preg_replace('/.*\$/', '$', $seenOptional) ?? $seenOptional),
                    ];
                    $seenOptional = null; // report only once per signature
                }
            }
        }

        return $issues;
    }

    // ── Aggregated file-level scan ────────────────────────────────────────────

    /**
     * Runs a single scanner callback over all files produced by an iterator.
     *
     * @param \Iterator        $files    Iterator of \SplFileInfo objects
     * @param callable         $scanner  static method reference e.g. [ScannerTrait::class, 'scanNullable']
     * @param string           $baseDir  Used to produce relative paths in results
     *
     * @return array<string, array>  Keyed by relative file path; value is the array of issues for that file.
     */
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
