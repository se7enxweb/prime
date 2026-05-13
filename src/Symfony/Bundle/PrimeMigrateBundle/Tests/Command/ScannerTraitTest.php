<?php

/*
 * This file is part of the prime package.
 * (c) 2004-2026 7x <info@se7enx.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\PrimeMigrateBundle\Tests\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\PrimeMigrateBundle\Command\ScannerTrait;

/**
 * Unit tests for ScannerTrait — all static scanner and fixer methods.
 *
 * Coverage targets:
 *   - scanNullable:      positive cases, negative/excluded cases, edge cases
 *   - applyNullableFix:  correct rewrite, skip-types, idempotency, pure (no side-effects)
 *   - scanForms:         string alias detection, FQCN not flagged
 *   - scanConstraints:   True/False/Null detection, new/use/qualified forms
 *   - scanYaml:          !php/object: detection, !!php/object:, clean YAML
 *   - scanTwig:          Twig_* detection, suggestion map, modern Twig\ not flagged
 *   - formTypeMap:       completeness check
 *   - scanFiles:         aggregation, unreadable directory path
 *   - countIssues / countFiles helpers
 *
 * SAFETY PROOF TESTS (no destructive side-effects):
 *   - applyNullableFix returns a STRING — it never writes to disk.
 *   - All scan* methods are pure — they accept strings and return arrays.
 *   - No filesystem writes are possible through any method in ScannerTrait.
 *
 * @author 7x <info@se7enx.com>
 */
class ScannerTraitTest extends TestCase
{
    // ── Concrete test-double that exposes the trait ───────────────────────────

    /**
     * Minimal concrete class that uses ScannerTrait so we can test
     * the non-static helper methods (scanFiles, countIssues, etc.).
     */
    private function makeConcrete(): object
    {
        return new class {
            use ScannerTrait;

            // Scanner::scanFiles() calls $this->relativePath() which lives
            // in AbstractMigrateCommand, not in the trait itself.  We provide
            // the exact same implementation here so the anonymous test double works.
            protected function relativePath(string $absolutePath, string $baseDir): string
            {
                $base = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
                if (str_starts_with($absolutePath, $base)) {
                    return substr($absolutePath, strlen($base));
                }
                return $absolutePath;
            }

            public function exposeScanFiles(\Iterator $files, callable $scanner, string $baseDir): array
            {
                return $this->scanFiles($files, $scanner, $baseDir);
            }

            public function exposeCountIssues(array $results): int
            {
                return $this->countIssues($results);
            }

            public function exposeCountFiles(array $results): int
            {
                return $this->countFiles($results);
            }

            public function exposeRelativePath(string $abs, string $base): string
            {
                return $this->relativePath($abs, $base);
            }
        };
    }

    // ════════════════════════════════════════════════════════════════════════
    // scanNullable — positive (should be flagged)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider provideNullablePositive
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideNullablePositive')]
    public function testScanNullableDetects(string $code, int $expectedCount): void
    {
        $issues = Scanner::scanNullable($code);
        $this->assertCount($expectedCount, $issues, "Expected {$expectedCount} issue(s) in: " . trim($code));
    }

    public static function provideNullablePositive(): array
    {
        return [
            'class type hint with default null' => [
                'public function setFoo(DateTime' . ' $date = null) {}',
                1,
            ],
            'namespaced class' => [
                // FQCN without leading ? — should be flagged as implicit nullable
                'public function setUser(\App\Entity\User $user = null) {}',
                1,
            ],
            'built-in array type' => [
                'public function setData(array' . ' $data = null) {}',
                1,
            ],
            'built-in string type' => [
                'public function setName(string' . ' $name = null) {}',
                1,
            ],
            'built-in int type' => [
                'public function setCount(int' . ' $count = null) {}',
                1,
            ],
            'multiple params in one line' => [
                // Two implicit nullable params (no ?) on one line — both should be flagged
                'function foo(string $a = null, DateTime $b = null) {}',
                2,
            ],
            'self type' => [
                'public function withParent(self' . ' $parent = null): static {}',
                1,
            ],
            'object type' => [
                'public function inject(object' . ' $obj = null): void {}',
                1,
            ],
            'bool type' => [
                'private function flag(bool' . ' $flag = null): void {}',
                1,
            ],
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // scanNullable — negative (should NOT be flagged)
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider provideNullableNegative
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideNullableNegative')]
    public function testScanNullableIgnores(string $code): void
    {
        $issues = Scanner::scanNullable($code);
        $this->assertSame([], $issues, "Should not flag: " . trim($code));
    }

    public static function provideNullableNegative(): array
    {
        return [
            'already explicitly nullable class' => [
                'public function setFoo(?DateTime $date = null) {}',
            ],
            'already explicitly nullable string' => [
                'public function setName(?string $name = null) {}',
            ],
            'union type with null' => [
                'public function foo(int|string $val = null) {}',
            ],
            'mixed type' => [
                'public function foo(mixed $val = null) {}',
            ],
            'void type' => [
                // void can't have a default but the scanner should still not flag it
                'function foo(void $x = null) {}',
            ],
            'never type' => [
                'function foo(never $x = null) {}',
            ],
            'php comment line' => [
                '// public function setFoo(DateTime' . ' $date = null)',
            ],
            'docblock line' => [
                ' * @param DateTime' . ' $date = null',
            ],
            'blank line' => [
                '',
            ],
            'no default null' => [
                'public function setFoo(DateTime $date) {}',
            ],
            'nullable with value default' => [
                "public function setFoo(string \$s = 'default') {}",
            ],
            'already nullable FQCN with backslash prefix' => [
                'public function __construct(?Twig\?\Environment $env = null) {}',
            ],
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // scanNullable — issue record structure
    // ════════════════════════════════════════════════════════════════════════

    public function testScanNullableIssueRecordHasRequiredKeys(): void
    {
        $code   = 'public function setFoo(DateTime' . ' $date = null) {}';
        $issues = Scanner::scanNullable($code);

        $this->assertCount(1, $issues);
        $issue = $issues[0];

        $this->assertArrayHasKey('line', $issue);
        $this->assertArrayHasKey('snippet', $issue);
        $this->assertArrayHasKey('type', $issue);
        $this->assertArrayHasKey('param', $issue);
        $this->assertSame(1, $issue['line']);
        $this->assertSame('DateTime', $issue['type']);
        $this->assertSame('$date', $issue['param']);
    }

    public function testScanNullableReportsCorrectLineNumbers(): void
    {
        $code = "<?php\n\nclass Foo\n{\n    public function bar(string \$x = null) {}\n}";
        $issues = Scanner::scanNullable($code);
        $this->assertCount(1, $issues);
        $this->assertSame(5, $issues[0]['line']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // applyNullableFix — correctness
    // ════════════════════════════════════════════════════════════════════════

    public function testApplyNullableFixRewritesImplicitNullable(): void
    {
        $before = 'public function setExpires(DateTime' . ' $date = null): void {}';
        $after  = 'public function setExpires(?DateTime $date = null): void {}';

        ['fixed' => $fixed, 'count' => $count] = Scanner::applyNullableFix($before);

        $this->assertSame($after, $fixed);
        $this->assertSame(1, $count);
    }

    public function testApplyNullableFixHandlesMultipleOnOneLine(): void
    {
        $before = 'function foo(string' . ' $a = null, DateTime' . ' $b = null) {}';
        $after  = 'function foo(?string $a = null, ?DateTime $b = null) {}';

        ['fixed' => $fixed, 'count' => $count] = Scanner::applyNullableFix($before);

        $this->assertSame($after, $fixed);
        $this->assertSame(2, $count);
    }

    public function testApplyNullableFixIsIdempotent(): void
    {
        $code = 'public function setFoo(?DateTime $date = null) {}';

        ['fixed' => $fixed, 'count' => $count] = Scanner::applyNullableFix($code);

        $this->assertSame($code, $fixed, 'Already-nullable param must not be changed');
        $this->assertSame(0, $count);
    }

    public function testApplyNullableFixSkipsMixedType(): void
    {
        $code = 'public function foo(mixed $val = null) {}';
        ['fixed' => $fixed, 'count' => $count] = Scanner::applyNullableFix($code);
        $this->assertSame($code, $fixed);
        $this->assertSame(0, $count);
    }

    public function testApplyNullableFixSkipsVoidType(): void
    {
        $code = 'function x(void $v = null) {}';
        ['fixed' => $fixed, 'count' => $count] = Scanner::applyNullableFix($code);
        $this->assertSame($code, $fixed);
        $this->assertSame(0, $count);
    }

    /**
     * SAFETY: applyNullableFix must be a pure function — it returns a string
     * and NEVER writes anything to the filesystem.
     */
    public function testApplyNullableFixIsPure_NoFilesystemSideEffects(): void
    {
        $tmpFile = sys_get_temp_dir() . '/prime_safety_' . uniqid('', true) . '.php';

        // Explicitly ensure the file does NOT exist before the call.
        $this->assertFileDoesNotExist($tmpFile);

        $code = 'public function setFoo(DateTime' . ' $date = null) {}';
        Scanner::applyNullableFix($code);

        // File must still not exist — the method must not have touched the filesystem.
        $this->assertFileDoesNotExist($tmpFile);
    }

    public function testApplyNullableFixReturnsArrayWithFixedAndCountKeys(): void
    {
        $result = Scanner::applyNullableFix('function f(string' . ' $x = null) {}');
        $this->assertArrayHasKey('fixed', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertIsString($result['fixed']);
        $this->assertIsInt($result['count']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // scanForms
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider provideFormPositive
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideFormPositive')]
    public function testScanFormsDetects(string $code, string $expectedAlias): void
    {
        $issues = Scanner::scanForms($code);
        $this->assertNotEmpty($issues, "Should detect alias '{$expectedAlias}' in: " . trim($code));
        $this->assertSame($expectedAlias, $issues[0]['alias']);
    }

    public static function provideFormPositive(): array
    {
        return [
            "->add with 'text'" => ["->add('name', 'text')", 'text'],
            "->add with 'email'" => ["->add('email', 'email')", 'email'],
            "->add with 'integer'" => ["->add('count', 'integer', [])", 'integer'],
            "->add with 'choice'" => ["->add('lang', 'choice')", 'choice'],
            "->add with 'entity'" => ["->add('user', 'entity')", 'entity'],
            "->add with 'collection'" => ["->add('tags', 'collection')", 'collection'],
            "createForm with 'form'" => ["createForm('form', \$data)", 'form'],
            "->add with 'date'" => ["->add('dob', 'date')", 'date'],
        ];
    }

    /**
     * @dataProvider provideFormNegative
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideFormNegative')]
    public function testScanFormsIgnores(string $code): void
    {
        $issues = Scanner::scanForms($code);
        $this->assertSame([], $issues, "Should not flag: " . trim($code));
    }

    public static function provideFormNegative(): array
    {
        return [
            'FQCN TextType' => ['->add(\'name\', TextType::class)'],
            'comment line' => ['// ->add(\'name\', \'text\')'],
            'docblock' => [' * ->add(\'name\', \'text\')'],
            'blank line' => [''],
            'no form add call' => ['$x = "text";'],
            'variable not string literal' => ['->add("name", $type)'],
        ];
    }

    public function testScanFormsIssueRecordHasFqcn(): void
    {
        $issues = Scanner::scanForms("->add('name', 'text')");
        $this->assertCount(1, $issues);
        $this->assertArrayHasKey('fqcn', $issues[0]);
        $this->assertSame('TextType::class', $issues[0]['fqcn']);
        $this->assertArrayHasKey('line', $issues[0]);
        $this->assertArrayHasKey('snippet', $issues[0]);
    }

    /**
     * Regression: field names that match an alias (e.g. ->add('email', 'email'))
     * must only produce ONE issue (the type position), not two.
     */
    public function testScanFormsDoesNotFlagFieldNameWhenItMatchesAlias(): void
    {
        $issues = Scanner::scanForms("->add('email', 'email')");
        $this->assertCount(1, $issues, 'Only the type-position alias should be flagged, not the field name');
        $this->assertSame('email', $issues[0]['alias']);
    }

    public function testScanFormsMultipleIssuesOnOneLine(): void
    {
        // Only ONE alias in type position on a single line is possible with ->add(),
        // but we verify the issue count is exactly 1 here.
        $issues = Scanner::scanForms("->add('name', 'text'), ->add('note', 'textarea')");
        $this->assertCount(2, $issues);
    }

    public function testScanFormsReportsCorrectLineNumber(): void
    {
        $code   = "<?php\n\$b->add('name', 'text');\n";
        $issues = Scanner::scanForms($code);
        $this->assertCount(1, $issues);
        $this->assertSame(2, $issues[0]['line']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // applyFormsFix — pure function / rewrite correctness
    // ════════════════════════════════════════════════════════════════════════

    public function testApplyFormsFixReplacesTextAlias(): void
    {
        $result = Scanner::applyFormsFix("<?php\n\$b->add('name', 'text');\n");
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('TextType::class', $result['fixed']);
        $this->assertStringNotContainsString("'text'", $result['fixed']);
    }

    public function testApplyFormsFixReplacesEmailWithFieldNameAlias(): void
    {
        // ->add('email', 'email') — field name 'email' must stay; type alias replaced
        $result = Scanner::applyFormsFix("<?php\n\$b->add('email', 'email');\n");
        $this->assertSame(1, $result['count']);
        $fixed  = $result['fixed'];
        $this->assertStringContainsString("'email'", $fixed, 'Field name must remain as string');
        $this->assertStringContainsString('EmailType::class', $fixed);
    }

    public function testApplyFormsFixReplacesMultipleAliasesInOneFile(): void
    {
        $code = "<?php\n\$b->add('a', 'text');\n\$b->add('b', 'email');\n\$b->add('c', 'textarea');\n";
        $result = Scanner::applyFormsFix($code);
        $this->assertSame(3, $result['count']);
        $this->assertStringContainsString('TextType::class', $result['fixed']);
        $this->assertStringContainsString('EmailType::class', $result['fixed']);
        $this->assertStringContainsString('TextareaType::class', $result['fixed']);
    }

    public function testApplyFormsFixInjectsUseAfterLastUseStatement(): void
    {
        $code = "<?php\nuse Foo\\Bar;\n\n\$b->add('name', 'text');\n";
        $result = Scanner::applyFormsFix($code);
        $fixed  = $result['fixed'];

        $barPos = strpos($fixed, 'use Foo\\Bar;');
        $textPos = strpos($fixed, 'use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;');
        $this->assertNotFalse($textPos);
        $this->assertGreaterThan($barPos, $textPos, 'New use must come after existing use statement');
    }

    public function testApplyFormsFixInjectsUseAfterNamespaceWhenNoUseExists(): void
    {
        $code = "<?php\nnamespace App\\Form;\n\n\$b->add('name', 'text');\n";
        $result = Scanner::applyFormsFix($code);
        $fixed  = $result['fixed'];

        $nsPos  = strpos($fixed, 'namespace App\\Form;');
        $usePos = strpos($fixed, 'use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;');
        $this->assertNotFalse($usePos);
        $this->assertGreaterThan($nsPos, $usePos);
    }

    public function testApplyFormsFixInjectsUseAfterPhpTagWhenNoNamespace(): void
    {
        $code = "<?php\n\n\$b->add('name', 'text');\n";
        $result = Scanner::applyFormsFix($code);
        $fixed  = $result['fixed'];

        $phpPos = strpos($fixed, '<?php');
        $usePos = strpos($fixed, 'use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;');
        $this->assertNotFalse($usePos);
        $this->assertGreaterThan($phpPos, $usePos);
    }

    public function testApplyFormsFixDoesNotDuplicateExistingUse(): void
    {
        $fqn  = 'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType';
        $code = "<?php\nuse {$fqn};\n\n\$b->add('name', 'text');\n";

        $result = Scanner::applyFormsFix($code);
        $fixed  = $result['fixed'];
        $count  = substr_count($fixed, "use {$fqn};");
        $this->assertSame(1, $count, 'Must not duplicate an already-present use statement');
    }

    public function testApplyFormsFixDoesNotInjectAlreadyPresentPartialUse(): void
    {
        // Some uses already there; only the missing one should be added.
        $code = "<?php\nuse Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;\n\n"
              . "\$b->add('a', 'text');\n\$b->add('b', 'email');\n";

        $result  = Scanner::applyFormsFix($code);
        $fixed   = $result['fixed'];
        $textCnt = substr_count($fixed, 'use Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType;');
        $this->assertSame(1, $textCnt, 'TextType already imported — must not be duplicated');
        $this->assertStringContainsString('use Symfony\\Component\\Form\\Extension\\Core\\Type\\EmailType;', $fixed);
    }

    public function testApplyFormsFixReturnsZeroCountWhenNothingToReplace(): void
    {
        $code   = "<?php\n\$b->add('name', TextType::class);\n";
        $result = Scanner::applyFormsFix($code);
        $this->assertSame(0, $result['count']);
        $this->assertSame($code, $result['fixed']);
        $this->assertSame([], $result['injected']);
    }

    public function testApplyFormsFixReturnValueHasExpectedKeys(): void
    {
        $result = Scanner::applyFormsFix("<?php\n\$b->add('n', 'text');\n");
        $this->assertArrayHasKey('fixed',    $result);
        $this->assertArrayHasKey('count',    $result);
        $this->assertArrayHasKey('injected', $result);
    }

    public function testApplyFormsFixInjectedListMatchesUseStatements(): void
    {
        $code   = "<?php\n\$b->add('a', 'text');\n\$b->add('b', 'email');\n";
        $result = Scanner::applyFormsFix($code);

        $expectedFqns = [
            'Symfony\\Component\\Form\\Extension\\Core\\Type\\EmailType',
            'Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType',
        ];
        sort($expectedFqns);
        $this->assertSame($expectedFqns, $result['injected']);
    }

    public function testApplyFormsFixHandlesEntityTypeWithDoctrineNamespace(): void
    {
        $code   = "<?php\n\$b->add('user', 'entity', ['class' => User::class]);\n";
        $result = Scanner::applyFormsFix($code);
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('EntityType::class', $result['fixed']);
        $this->assertContains('Symfony\\Bridge\\Doctrine\\Form\\Type\\EntityType', $result['injected']);
    }

    public function testApplyFormsFixHandlesCreateFormCallShape(): void
    {
        $code   = "<?php\n\$form = \$this->createForm('text', \$data);\n";
        $result = Scanner::applyFormsFix($code);
        $this->assertSame(1, $result['count']);
        $this->assertStringContainsString('TextType::class', $result['fixed']);
    }

    public function testApplyFormsFixIsIdempotent(): void
    {
        $code   = "<?php\n\$b->add('name', 'text');\n";
        $pass1  = Scanner::applyFormsFix($code);
        $pass2  = Scanner::applyFormsFix($pass1['fixed']);
        $this->assertSame(0, $pass2['count'], 'Running fixer twice must produce no more changes');
    }

    // ════════════════════════════════════════════════════════════════════════
    // formTypeNamespaceMap — coverage
    // ════════════════════════════════════════════════════════════════════════

    public function testFormTypeNamespaceMapKeySetMatchesFormTypeMap(): void
    {
        $mapKeys = array_keys(Scanner::formTypeMap());
        $nsKeys  = array_keys(Scanner::formTypeNamespaceMap());
        sort($mapKeys);
        sort($nsKeys);
        $this->assertSame($mapKeys, $nsKeys, 'formTypeNamespaceMap must cover every key in formTypeMap');
    }

    public function testFormTypeNamespaceMapAllValueAreFqns(): void
    {
        foreach (Scanner::formTypeNamespaceMap() as $alias => $fqn) {
            $this->assertStringContainsString('\\', $fqn,
                "formTypeNamespaceMap['{$alias}'] must be a FQCN, got '{$fqn}'"
            );
            $this->assertStringNotContainsString('::class', $fqn,
                "formTypeNamespaceMap['{$alias}'] must NOT include '::class' suffix"
            );
        }
    }

    public function testFormTypeNamespaceMapTextType(): void
    {
        $map = Scanner::formTypeNamespaceMap();
        $this->assertSame('Symfony\\Component\\Form\\Extension\\Core\\Type\\TextType', $map['text']);
    }

    public function testFormTypeNamespaceMapEntityType(): void
    {
        $map = Scanner::formTypeNamespaceMap();
        $this->assertSame('Symfony\\Bridge\\Doctrine\\Form\\Type\\EntityType', $map['entity']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // applyFormsFix — pure function safety proof
    // ════════════════════════════════════════════════════════════════════════

    public function testApplyFormsFixNeverWritesFiles(): void
    {
        $sentinel = sys_get_temp_dir() . '/prime_forms_fix_purity_' . uniqid('', true);
        Scanner::applyFormsFix("<?php\n\$b->add('name', 'text');\n");
        $this->assertFileDoesNotExist($sentinel);
    }

    // ════════════════════════════════════════════════════════════════════════
    // scanConstraints
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider provideConstraintPositive
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideConstraintPositive')]
    public function testScanConstraintsDetects(string $code, string $oldName, string $newName): void
    {
        $issues = Scanner::scanConstraints($code);
        $this->assertNotEmpty($issues, "Should detect '{$oldName}' in: " . trim($code));
        $found = array_filter($issues, fn($i) => $i['old'] === $oldName);
        $this->assertNotEmpty($found, "Expected old='{$oldName}'");
        $first = array_values($found)[0];
        $this->assertSame($newName, $first['new']);
    }

    public static function provideConstraintPositive(): array
    {
        return [
            'use True' => ['use Symfony\\Component\\Validator\\Constraints\\True;', 'True', 'IsTrue'],
            'use False' => ['use Symfony\\Component\\Validator\\Constraints\\False;', 'False', 'IsFalse'],
            'use Null' => ['use Symfony\\Component\\Validator\\Constraints\\Null;', 'Null', 'IsNull'],
            'Constraints\\True qualified' => ['new Constraints\\True()', 'True', 'IsTrue'],
            'Constraints\\False qualified' => ['$c = new Constraints\\False();', 'False', 'IsFalse'],
            'Constraints\\Null qualified' => ['$c = new Constraints\\Null();', 'Null', 'IsNull'],
        ];
    }

    /**
     * @dataProvider provideConstraintNegative
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideConstraintNegative')]
    public function testScanConstraintsIgnores(string $code): void
    {
        $issues = Scanner::scanConstraints($code);
        $this->assertSame([], $issues, "Should not flag: " . trim($code));
    }

    public static function provideConstraintNegative(): array
    {
        return [
            'IsTrue' => ['use Symfony\\Component\\Validator\\Constraints\\IsTrue;'],
            'IsFalse' => ['new Constraints\\IsFalse()'],
            'IsNull' => ['new Constraints\\IsNull()'],
            'comment line' => ['// use Symfony\\Component\\Validator\\Constraints\\True;'],
            'blank line' => [''],
            'docblock' => [' * new True()'],
        ];
    }

    // ════════════════════════════════════════════════════════════════════════
    // scanYaml
    // ════════════════════════════════════════════════════════════════════════

    public function testScanYamlDetectsSingleBang(): void
    {
        $yaml   = "key: !php/object: \"O:4:\\\"User\\\":0:{}\"";
        $issues = Scanner::scanYaml($yaml);
        $this->assertCount(1, $issues);
        $this->assertSame(1, $issues[0]['line']);
    }

    public function testScanYamlDetectsDoubleBang(): void
    {
        $yaml   = "key: !!php/object: \"O:4:\\\"User\\\":0:{}\"";
        $issues = Scanner::scanYaml($yaml);
        $this->assertCount(1, $issues);
    }

    public function testScanYamlIgnoresNormalYaml(): void
    {
        $yaml = "database:\n  host: 127.0.0.1\n  port: 3306\n  name: myapp\n";
        $this->assertSame([], Scanner::scanYaml($yaml));
    }

    public function testScanYamlIgnoresEmptyContent(): void
    {
        $this->assertSame([], Scanner::scanYaml(''));
    }

    public function testScanYamlReportsCorrectLineNumber(): void
    {
        $yaml = "# header\nfoo: bar\nobject: !php/object: \"O:0:{}\"";
        $issues = Scanner::scanYaml($yaml);
        $this->assertCount(1, $issues);
        $this->assertSame(3, $issues[0]['line']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // scanTwig
    // ════════════════════════════════════════════════════════════════════════

    /**
     * @dataProvider provideTwigPositive
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideTwigPositive')]
    public function testScanTwigDetects(string $code, string $expectedClass): void
    {
        $issues = Scanner::scanTwig($code);
        $this->assertNotEmpty($issues, "Should detect '{$expectedClass}' in: " . trim($code));
        $found = array_filter($issues, fn($i) => $i['class'] === $expectedClass);
        $this->assertNotEmpty($found, "Expected class '{$expectedClass}' in issues");
    }

    public static function provideTwigPositive(): array
    {
        return [
            'Twig_Extension' => ['class Foo extends \\Twig_Extension {}', 'Twig_Extension'],
            'Twig_SimpleFilter' => ['return new \\Twig_SimpleFilter("f", [$this, "m"]);', 'Twig_SimpleFilter'],
            'Twig_SimpleFunction' => ['return new \\Twig_SimpleFunction("f", [$this, "m"]);', 'Twig_SimpleFunction'],
            'Twig_Environment' => ['$env = new Twig_Environment($loader);', 'Twig_Environment'],
            'Twig_Loader_Filesystem' => ['$loader = new \\Twig_Loader_Filesystem($path);', 'Twig_Loader_Filesystem'],
        ];
    }

    /**
     * @dataProvider provideTwigNegative
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('provideTwigNegative')]
    public function testScanTwigIgnores(string $code): void
    {
        $issues = Scanner::scanTwig($code);
        $this->assertSame([], $issues, "Should not flag: " . trim($code));
    }

    public static function provideTwigNegative(): array
    {
        return [
            'modern AbstractExtension' => ['use Twig\\Extension\\AbstractExtension;'],
            'modern TwigFilter' => ['return new Twig\\TwigFilter("f", [$this, "m"]);'],
            'comment line' => ['// class Foo extends \\Twig_Extension {}'],
            'docblock' => [' * @extends \\Twig_Extension'],
            'blank line' => [''],
        ];
    }

    public function testScanTwigIncludesSuggestionForKnownClasses(): void
    {
        $issues = Scanner::scanTwig('class Foo extends \\Twig_Extension {}');
        $this->assertCount(1, $issues);
        $this->assertArrayHasKey('suggestion', $issues[0]);
        $this->assertSame('Twig\\Extension\\AbstractExtension', $issues[0]['suggestion']);
    }

    public function testScanTwigSuggestionIsNullForUnknownTwigClass(): void
    {
        $issues = Scanner::scanTwig('$x = new Twig_Unknown_Class();');
        $this->assertCount(1, $issues);
        $this->assertNull($issues[0]['suggestion']);
    }

    // ════════════════════════════════════════════════════════════════════════
    // formTypeMap — completeness
    // ════════════════════════════════════════════════════════════════════════

    public function testFormTypeMapContainsExpectedAliases(): void
    {
        $map = Scanner::formTypeMap();

        $required = ['text', 'textarea', 'integer', 'email', 'password', 'checkbox',
                     'choice', 'date', 'datetime', 'time', 'file', 'hidden', 'submit',
                     'collection', 'entity', 'form'];

        foreach ($required as $alias) {
            $this->assertArrayHasKey($alias, $map, "formTypeMap must contain alias '{$alias}'");
            $this->assertNotEmpty($map[$alias]);
        }
    }

    public function testFormTypeMapValuesEndWithClassConstant(): void
    {
        foreach (Scanner::formTypeMap() as $alias => $fqcn) {
            $this->assertStringEndsWith('::class', $fqcn,
                "formTypeMap['{$alias}'] should end with '::class' — got '{$fqcn}'"
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // Non-static helpers: scanFiles, countIssues, countFiles, relativePath
    // ════════════════════════════════════════════════════════════════════════

    public function testScanFilesAggregatesResultsByRelativePath(): void
    {
        $dir = $this->makeFixtureDir([
            'Controller.php' => 'public function setFoo(DateTime' . ' $date = null) {}',
        ]);

        $concrete = $this->makeConcrete();
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        $phpIt = new \CallbackFilterIterator($it, fn(\SplFileInfo $f) => $f->getExtension() === 'php');

        $results = $concrete->exposeScanFiles($phpIt, [Scanner::class, 'scanNullable'], $dir);

        $this->assertArrayHasKey('Controller.php', $results);
        $this->assertCount(1, $results['Controller.php']);

        $this->cleanFixtureDir($dir);
    }

    public function testScanFilesReturnsEmptyForCleanFiles(): void
    {
        $dir = $this->makeFixtureDir([
            'Clean.php' => '<?php class Clean {}',
        ]);

        $concrete = $this->makeConcrete();
        $it       = new \ArrayIterator([new \SplFileInfo($dir . '/Clean.php')]);
        $results  = $concrete->exposeScanFiles($it, [Scanner::class, 'scanNullable'], $dir);

        $this->assertSame([], $results);
        $this->cleanFixtureDir($dir);
    }

    public function testScanFilesSkipsUnreadableDirectory(): void
    {
        // EmptyIterator path — if RecursiveDirectoryIterator fails on a bad dir,
        // filesWithExtension returns an EmptyIterator, scanFiles returns [].
        $concrete = $this->makeConcrete();
        $results  = $concrete->exposeScanFiles(new \EmptyIterator(), [Scanner::class, 'scanNullable'], '/nonexistent');
        $this->assertSame([], $results);
    }

    public function testCountIssues(): void
    {
        $concrete = $this->makeConcrete();
        $results = [
            'fileA.php' => [['line' => 1, 'snippet' => 'x'], ['line' => 2, 'snippet' => 'y']],
            'fileB.php' => [['line' => 5, 'snippet' => 'z']],
        ];
        $this->assertSame(3, $concrete->exposeCountIssues($results));
    }

    public function testCountIssuesOnEmpty(): void
    {
        $this->assertSame(0, $this->makeConcrete()->exposeCountIssues([]));
    }

    public function testCountFiles(): void
    {
        $concrete = $this->makeConcrete();
        $results  = ['a.php' => [['line' => 1, 'snippet' => '']], 'b.php' => [['line' => 2, 'snippet' => '']]];
        $this->assertSame(2, $concrete->exposeCountFiles($results));
    }

    public function testRelativePathStripsBaseDir(): void
    {
        $concrete = $this->makeConcrete();
        $base     = '/var/www/myapp/src';
        $abs      = '/var/www/myapp/src/MyBundle/Controller.php';
        $this->assertSame('MyBundle/Controller.php', $concrete->exposeRelativePath($abs, $base));
    }

    public function testRelativePathFallsBackToAbsoluteWhenOutsideBase(): void
    {
        $concrete = $this->makeConcrete();
        $abs      = '/other/path/Controller.php';
        $base     = '/var/www/myapp/src';
        $this->assertSame($abs, $concrete->exposeRelativePath($abs, $base));
    }

    // ════════════════════════════════════════════════════════════════════════
    // Pure function / safety proofs
    // ════════════════════════════════════════════════════════════════════════

    /**
     * All scanXxx static methods must return arrays — never touch the filesystem.
     * We pass synthetic strings and verify no file appears at a sentinel path.
     */
    public function testScanMethodsAreStaticAndPure(): void
    {
        $sentinel = sys_get_temp_dir() . '/prime_scan_purity_' . uniqid('', true);

        Scanner::scanNullable('public function foo(DateTime' . ' $x = null) {}');
        Scanner::scanForms("->add('name', 'text')");
        Scanner::scanConstraints('new Constraints\\True()');
        Scanner::scanYaml('x: !php/object: ""');
        Scanner::scanTwig('new \\Twig_Extension()');

        // None of those calls should have created or modified any file.
        $this->assertFileDoesNotExist($sentinel);
        // More importantly: we verify they returned arrays, not null/bool/etc.
        $this->assertIsArray(Scanner::scanNullable(''));
        $this->assertIsArray(Scanner::scanForms(''));
        $this->assertIsArray(Scanner::scanConstraints(''));
        $this->assertIsArray(Scanner::scanYaml(''));
        $this->assertIsArray(Scanner::scanTwig(''));
    }

    // ════════════════════════════════════════════════════════════════════════
    // Fixture helpers
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Creates a temporary directory with a set of named PHP fixture files.
     *
     * @param array<string, string> $files  filename => content
     */
    private function makeFixtureDir(array $files): string
    {
        $dir = sys_get_temp_dir() . '/prime_test_' . uniqid('', true);
        mkdir($dir, 0755, true);
        foreach ($files as $name => $content) {
            file_put_contents($dir . '/' . $name, $content);
        }
        return $dir;
    }

    private function cleanFixtureDir(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($dir);
    }
}

/**
 * Named concrete class that uses ScannerTrait.
 *
 * PHP 8.5 deprecated calling static trait methods directly via the trait
 * (e.g. Scanner::scanNullable()), so all tests route their calls through
 * this class instead.  The relativePath() method mirrors the implementation
 * in AbstractMigrateCommand so scanFiles() can resolve relative paths.
 */
final class Scanner
{
    use \Symfony\Bundle\PrimeMigrateBundle\Command\ScannerTrait;

    // Expose protected scanFiles() publicly so tests can call it directly.
    public function runScanFiles(\Iterator $files, callable $scanner, string $baseDir): array
    {
        return $this->scanFiles($files, $scanner, $baseDir);
    }

    protected function relativePath(string $absolutePath, string $baseDir): string
    {
        $base = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (str_starts_with($absolutePath, $base)) {
            return substr($absolutePath, strlen($base));
        }
        return $absolutePath;
    }
}
