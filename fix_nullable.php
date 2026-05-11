<?php
/**
 * fix_nullable.php — PHP 8.4+ implicit nullable fix
 *
 * Converts:  function foo(SomeType $param = null)
 * To:        function foo(?SomeType $param = null)
 *
 * Strategy:
 *  - Use token_get_all() to identify T_FUNCTION tokens
 *  - Then textually fix only function/method parameter lists using a regex
 *    that requires the type to be preceded by '(' or ','
 *  - This prevents matching class properties like "public static $foo = null"
 */

$files = [];
$iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('src'));
foreach ($iter as $file) {
    if ($file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}

// Pattern: type immediately after ( or , (with optional whitespace), followed by $param = null
// Lookbehind ensures we're inside a parameter list.
// Does NOT match ?Type (already nullable) or union types.
// Does NOT match bare `static`, `self`, `parent` used as method modifiers.
$pattern = '/([,(]\s*)(?<!\?)([A-Za-z_][A-Za-z0-9_\\\\]*)(\s+\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*null\b)/';

$changed = 0;

foreach ($files as $path) {
    $src = file_get_contents($path);

    // Quick pre-check: must contain "= null" to be worth processing
    if (strpos($src, '= null') === false) {
        continue;
    }

    $new = preg_replace_callback($pattern, static function (array $m): string {
        // m[1] = leading (, or , with spaces
        // m[2] = type name
        // m[3] = space + $param = null
        // Don't double-add ?
        if ($m[2][0] === '?') {
            return $m[0];
        }
        return $m[1] . '?' . $m[2] . $m[3];
    }, $src);

    if ($new === null || $new === $src) {
        continue;
    }

    file_put_contents($path, $new);
    echo "Fixed: $path\n";
    $changed++;
}

echo "\nDone. $changed file(s) modified.\n";
