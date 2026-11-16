<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\AttributeArguments;
use Bidirekt\PhpSdk\Reader\ClassIndex;
use Bidirekt\PhpSdk\Reader\ClassReference;
use Bidirekt\PhpSdk\Reader\ConstantArray;
use Bidirekt\PhpSdk\Reader\ConstantExpressionToValue;
use Bidirekt\PhpSdk\Reader\ConstantValue;
use Bidirekt\PhpSdk\Reader\FieldConstruction;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use PhpParser\Node\Attribute;
use PhpParser\NodeFinder;
use PHPUnit\Framework\TestCase;

final class ConstantExpressionToValueTest extends TestCase
{
    private const PARAMETERS = ['method', 'path', 'responses'];

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/php-sdk-constant-expression-' . bin2hex(random_bytes(6));
        mkdir($this->root);
        $this->root = (string) realpath($this->root);
    }

    protected function tearDown(): void
    {
        foreach ((array) scandir($this->root) as $entry) {
            if (is_string($entry) && is_file($this->root . '/' . $entry)) {
                unlink($this->root . '/' . $entry);
            }
        }
        rmdir($this->root);
    }

    public function testNamesPositionalAndNamedArgumentsAfterTheParameters(): void
    {
        $arguments = $this->arguments("#[Handler('GET', responses: [], path: '/pets')]");

        self::assertSame(['method', 'responses', 'path'], array_keys($arguments->values));
        self::assertSame('GET', $arguments->values['method']->value);
        self::assertSame('/pets', $arguments->values['path']->value);
        self::assertSame(3, $arguments->location->line);
    }

    public function testKeepsEveryArrayEntryAsWrittenRepeatedAndMissingKeysIncluded(): void
    {
        $responses = $this->arguments("#[Handler(responses: [200 => Pet::class, '200' => 'Pet', Pet::class, -1 => null])]")->values['responses']->value;

        self::assertInstanceOf(ConstantArray::class, $responses);
        $keys = array_map(static fn($item) => $item->key?->value, $responses->items);
        self::assertSame([200, '200', null, -1], $keys);
        self::assertEquals(new ClassReference('App\\Pet'), $responses->items[0]->value->value);
        self::assertSame('Pet', $responses->items[1]->value->value);
        self::assertNull($responses->items[3]->value->value);
    }

    public function testReadsClassConstantsThroughParentsInterfacesAndOtherConstants(): void
    {
        $this->write('Codes.php', "<?php\nnamespace App;\n\ninterface StatusCodes\n{\n    const OK = self::SUCCESS;\n    const SUCCESS = 200;\n}\n\nclass BaseController implements StatusCodes\n{\n}\n");
        $values = $this->arguments(
            "#[Handler(method: self::METHOD, path: parent::PATH, responses: [self::OK => null])]",
            "class Base extends BaseController\n{\n    const PATH = '/pets';\n}\n\nfinal class Controller extends Base\n{\n    const METHOD = 'GET';\n\n    %s\n    public function list() {}\n}\n",
        )->values;

        self::assertSame('GET', $values['method']->value);
        self::assertSame('/pets', $values['path']->value);
        $responses = $values['responses']->value;
        self::assertInstanceOf(ConstantArray::class, $responses);
        self::assertSame(200, $responses->items[0]->key?->value);
    }

    public function testReadsTheValueOfABackedEnumCase(): void
    {
        $values = $this->arguments(
            '#[Handler(method: Method::Get->value)]',
            "enum Method: string\n{\n    case Get = 'GET';\n}\n\n%s\nfunction handler() {}\n",
        )->values;

        self::assertEquals(new ConstantValue('GET', new SourceLocation($this->root . '/subject.php', 8)), $values['method']);
    }

    public function testReadsTheArgumentsOfNewBidirektField(): void
    {
        $fields = $this->arguments("#[Handler(responses: [new \\Bidirekt\\PhpSdk\\BidirektField('petId', type: 'integer')])]")->values['responses']->value;

        self::assertInstanceOf(ConstantArray::class, $fields);
        $field = $fields->items[0]->value->value;
        self::assertInstanceOf(FieldConstruction::class, $field);
        self::assertSame('petId', $field->arguments->values['name']->value);
        self::assertSame('integer', $field->arguments->values['type']->value);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public function rejectedArguments(): iterable
    {
        yield 'unknown name' => ["#[Handler(methd: 'GET')]", 'unknown argument "methd" in #[Handler]: write method, path or responses'];
        yield 'too many' => ["#[Handler('GET', '/pets', [], null)]", '#[Handler] takes at most 3 arguments: remove the extra ones'];
        yield 'positional after named' => ["#[Handler(method: 'GET', '/pets')]", 'a positional argument follows a named one in #[Handler]: name it'];
        yield 'passed twice' => ["#[Handler('GET', method: 'POST')]", '"method" is passed more than once to #[Handler]: keep one'];
        yield 'function call' => ['#[Handler(method: strtoupper(\'get\'))]', '"strtoupper(\'get\')" cannot be read without running the code: write a literal, an array, X::class, a class constant or Enum::Case->value'];
        yield 'another new' => ['#[Handler(method: new Method())]', '"new Method()" cannot be read without running the code: write a literal, an array, X::class, a class constant or Enum::Case->value'];
        yield 'missing constant' => ['#[Handler(method: Loop::B)]', 'constant "Loop::B" is not declared: declare it in class "Loop" or one of its parents'];
        yield 'enum case without ->value' => ['#[Handler(method: Kind::Dog)]', '"Kind::Dog" is an enum case, not a value: write Kind::Dog->value'];
        yield 'missing class' => ['#[Handler(method: Missing::A)]', 'cannot find class "App\\Missing" of constant "Missing::A": declare it in the project or require its package with composer'];
    }

    /**
     * @dataProvider rejectedArguments
     */
    public function testRejectsWithTheLine(string $attribute, string $message): void
    {
        $this->write('Loop.php', "<?php\nnamespace App;\n\nfinal class Loop\n{\n    const A = self::C;\n    const C = self::A;\n}\n\nenum Kind: string\n{\n    case Dog = 'dog';\n}\n");
        $file = $this->parse('subject.php', "<?php\nnamespace App;\n" . $attribute . "\nfunction handler() {}\n");

        self::assertEquals([new Diagnostic($message, new SourceLocation($this->root . '/subject.php', 3))], $this->evaluate($file));
    }

    public function testRejectsAConstantThatRefersToItselfWhereTheLoopCloses(): void
    {
        $this->write('Loop.php', "<?php\nnamespace App;\n\nfinal class Loop\n{\n    const A = self::C;\n    const C = self::A;\n}\n");
        $file = $this->parse('subject.php', "<?php\nnamespace App;\n#[Handler(method: Loop::A)]\nfunction handler() {}\n");

        self::assertEquals([new Diagnostic('constant "Loop::A" refers to itself: give it a value', new SourceLocation($this->root . '/Loop.php', 7))], $this->evaluate($file));
    }

    /**
     * @param string $around the code around the attribute, `%s` where it goes
     */
    private function arguments(string $attribute, string $around = "%s\nfunction handler() {}\n"): AttributeArguments
    {
        $file = $this->parse('subject.php', "<?php\nnamespace App;\n" . sprintf($around, $attribute));
        $arguments = $this->evaluate($file);
        self::assertInstanceOf(AttributeArguments::class, $arguments);

        return $arguments;
    }

    /**
     * @return AttributeArguments|list<Diagnostic>
     */
    private function evaluate(ParsedFile $file): AttributeArguments|array
    {
        $parser = new PhpFileToParsed();
        $classes = new ClassIndex($parser, null);
        $classes->addParsedFile($file);
        foreach (['Codes.php', 'Loop.php'] as $other) {
            if (is_file($this->root . '/' . $other)) {
                $parsed = $parser->fileToParsed($this->root . '/' . $other);
                self::assertInstanceOf(ParsedFile::class, $parsed);
                $classes->addParsedFile($parsed);
            }
        }
        $attribute = (new NodeFinder())->findFirstInstanceOf($file->statements, Attribute::class);
        self::assertInstanceOf(Attribute::class, $attribute);

        return (new ConstantExpressionToValue($classes))
            ->argumentsToNamed($attribute->args, self::PARAMETERS, '#[Handler]', $file->locationOf($attribute), $file);
    }

    private function parse(string $name, string $code): ParsedFile
    {
        $this->write($name, $code);
        $parsed = (new PhpFileToParsed())->fileToParsed($this->root . '/' . $name);
        self::assertInstanceOf(ParsedFile::class, $parsed);

        return $parsed;
    }

    private function write(string $name, string $code): void
    {
        file_put_contents($this->root . '/' . $name, $code);
    }
}
