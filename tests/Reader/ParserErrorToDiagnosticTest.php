<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\ParserErrorToDiagnostic;
use PhpParser\Error;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

final class ParserErrorToDiagnosticTest extends TestCase
{
    private const FILE = '/project/pets.php';

    private const FIX = ': fix the code so PHP can parse it';

    /**
     * @return iterable<string, array{string, string}>
     */
    public function syntaxErrors(): iterable
    {
        yield 'punctuation' => ["<?php\n\$x = [1 => ];\n", 'unexpected token "]"'];
        yield 'attribute where a parameter ends' => ["<?php\nfunction f(int \$a #[A] \$b) {}\n", 'unexpected token "#[", expecting ")"'];
        yield 'keyword' => ["<?php\nnamespace A use B;\n", 'unexpected token "use", expecting ";" or "{"'];
        yield 'identifier' => ["<?php\n\$x = 1 pets;\n", 'unexpected identifier "pets"'];
        yield 'long identifier' => ["<?php\n\$x = 1 " . str_repeat('a', 40) . ";\n", 'unexpected identifier "' . str_repeat('a', 30) . '..."'];
        yield 'variable' => ["<?php\n\$x = 1 \$y;\n", 'unexpected variable "$y"'];
        yield 'integer and expected brace' => ["<?php\nmatch(\$x) { 1 => 2 3 };\n", 'unexpected integer "3", expecting "}"'];
        yield 'floating-point number' => ["<?php\n\$x = 1 1.5;\n", 'unexpected floating-point number "1.5"'];
        yield 'double-quoted string' => ["<?php\n\$x = 1 \"abc\";\n", 'unexpected double-quoted string "abc"'];
        yield 'single-quoted string' => ["<?php\n\$x = 1 'abc';\n", 'unexpected single-quoted string "abc"'];
        yield 'namespaced name' => ["<?php\n\$x = 1 Foo\\Bar;\n", 'unexpected namespaced name "Foo\\Bar"'];
        yield 'fully qualified name' => ["<?php\n\$x = 1 \\Foo\\Bar;\n", 'unexpected fully qualified name "\\Foo\\Bar"'];
        yield 'expected identifier or variable' => ["<?php\n\$a->;\n", 'unexpected token ";", expecting identifier or variable or "{" or "$"'];
        yield 'end of file' => ["<?php\n\$x = [1, 2", 'unexpected end of file, expecting "," or "]" or ")"'];
        yield 'not a syntax error' => ["<?php\nclass A { public public \$x; }\n", 'multiple access type modifiers are not allowed'];
    }

    /**
     * @dataProvider syntaxErrors
     */
    public function testTellsTheErrorAsPhpDoesWithWhatTheFileWrites(string $code, string $error): void
    {
        self::assertEquals(new Diagnostic('syntax error: ' . $error . self::FIX, new SourceLocation(self::FILE, 2)), $this->diagnose($code));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public function cutPaths(): iterable
    {
        yield 'tag above more tags' => [
            "<?php\n\n/**\n * @bidirekt provides GET /owners/*/pets\n * @bidirekt response 200 Pet[]\n */\nfunction pets() {}\n",
            '"*/" in "/owners/*/pets" ends the docblock: write "/owners/*\\/pets"',
            4,
        ];
        yield 'last tag of a method docblock' => [
            "<?php\nfinal class Owners\n{\n    /**\n     * @bidirekt provides GET /owners/*/pets\n     */\n    public function pets(): void {}\n}\n",
            '"*/" in "/owners/*/pets" ends the docblock: write "/owners/*\\/pets"',
            5,
        ];
        yield 'one-line docblock with two wildcards' => [
            "<?php\n/** @bidirekt provides GET /a/*/b/*/c */\nfunction f() {}\n",
            '"*/" in "/a/*/b/*/c" ends the docblock: write "/a/*\\/b/*\\/c"',
            2,
        ];
    }

    /**
     * @dataProvider cutPaths
     */
    public function testTellsAPathWhoseWildcardEndsTheDocblock(string $code, string $message, int $line): void
    {
        self::assertEquals(new Diagnostic($message, new SourceLocation(self::FILE, $line)), $this->diagnose($code));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public function ordinaryErrors(): iterable
    {
        yield 'a docblock without a tag' => ["<?php\n/**\n * Lists /owners/*/pets\n */\nfunction f() {}\n", 'unexpected token "/"', 4];
        yield 'an error below a closed docblock' => ["<?php\n/**\n * @bidirekt provides GET /owners\n */\nfunction f() {}\n\$x = 1 2;\n", 'unexpected integer "2"', 6];
        yield 'a tag line that ends the docblock' => ["<?php\n/** @bidirekt provides GET /owners */\n\$x = 1 2;\n", 'unexpected integer "2"', 3];
    }

    /**
     * @dataProvider ordinaryErrors
     */
    public function testLeavesOtherSyntaxErrorsAfterADocblockAsTheyAre(string $code, string $error, int $line): void
    {
        self::assertEquals(new Diagnostic('syntax error: ' . $error . self::FIX, new SourceLocation(self::FILE, $line)), $this->diagnose($code));
    }

    private function diagnose(string $code): Diagnostic
    {
        try {
            (new ParserFactory())->createForNewestSupportedVersion()->parse($code);
        } catch (Error $error) {
            return ParserErrorToDiagnostic::errorToDiagnostic($error, self::FILE, $code);
        }
        self::fail('the code parses');
    }
}
