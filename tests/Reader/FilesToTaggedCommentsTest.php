<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Reader\CommentTag;
use Bidirekt\PhpSdk\Reader\FilesToTaggedComments;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\ParsedFiles;
use Bidirekt\PhpSdk\Reader\TaggedComments;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PHPUnit\Framework\TestCase;

final class FilesToTaggedCommentsTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/php-sdk-tagged-comments-' . bin2hex(random_bytes(6)) . '.php';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function testReadsEachTagAtTheStartOfADocblockLineWithItsLine(): void
    {
        $read = $this->read("<?php\n/**\n * Finds a pet.\n *\n * @bidirekt provides GET /pets/*\n * @param string \$id\n * @bidirekt response 200 Pet\n */\nfunction findPet(string \$id) {}\n");

        self::assertCount(1, $read->comments);
        self::assertInstanceOf(Function_::class, $read->comments[0]->element);
        self::assertSame(
            [['provides', ['GET', '/pets/*'], 5], ['response', ['200', 'Pet'], 7]],
            self::tagsToRows($read->comments[0]->tags),
        );
    }

    public function testIgnoresTagsOutsideDocblocksAndInTheMiddleOfALine(): void
    {
        $read = $this->read("<?php\n/* @bidirekt provides GET /a */\nfunction a() {}\n// @bidirekt provides GET /b\nfunction b() {}\n# @bidirekt provides GET /c\nfunction c() {}\n/** See @bidirekt provides GET /d */\nfunction d() {}\n");

        self::assertSame([], $read->comments);
        self::assertSame([], $read->errors);
    }

    public function testATagRunsUntilTheNextTagAndTheEscapeReadsAsTheCommentEnd(): void
    {
        $read = $this->read("<?php\n/** @bidirekt provides GET /pets/*\\/photos\n *   extra\n * @bidirekt field description=\"Shown on the card\" union={card: Card, pix: Pix} */\nfunction photos() {}\n");

        self::assertSame(
            [['provides', ['GET', '/pets/*/photos', 'extra'], 2], ['field', ['description="Shown on the card"', 'union={card: Card, pix: Pix}'], 4]],
            self::tagsToRows($read->comments[0]->tags),
        );
    }

    public function testBindsADocblockBeforeAClosurePassedToACallToTheClosureAndAboveARouteCallToTheStatement(): void
    {
        $read = $this->read("<?php\n/** @bidirekt provides GET /a */\n\$app->get('/a', fn () => 1);\n\$app->get('/b', /** @bidirekt provides GET /b */ function () {});\n");

        self::assertInstanceOf(Expression::class, $read->comments[0]->element);
        self::assertInstanceOf(Closure::class, $read->comments[1]->element);
    }

    public function testErrorsOnAMissingOrUnknownKeywordAndMarksQuotedArguments(): void
    {
        $read = $this->read("<?php\n/**\n * @bidirekt\n * @bidirekt handles GET /pets\n * @bidirekt provides GET \"/pets\"\n */\nfunction pets() {}\n");

        self::assertSame(
            [
                [3, '"@bidirekt" needs a keyword: use provides, consumes, request, response, schema or field'],
                [4, 'unknown tag "@bidirekt handles": use provides, consumes, request, response, schema or field'],
            ],
            array_map(static fn(Diagnostic $error): array => [$error->location->line ?? 0, $error->message], $read->errors),
        );
        self::assertSame(
            'cannot read "@bidirekt provides GET "/pets"": remove the quotes, only description="<text>" takes them',
            $read->comments[0]->tags[0]->error?->message,
        );
    }

    public function testReadsADocblockWithCrlfLineEndingsAsOneWithLf(): void
    {
        $code = "<?php\n/**\n * @example\n * @bidirekt provides GET /example\n * @return void\n * @bidirekt provides GET /pets/*\n *   extra\n * @see the docs. @bidirekt response 200\n */\nfunction findPet() {}\n/** @bidirekt provides GET /end */\n";
        $lf = $this->read($code);
        $crlf = $this->read(str_replace("\n", "\r\n", $code));

        self::assertSame([['provides', ['GET', '/pets/*', 'extra'], 6]], self::tagsToRows($crlf->comments[0]->tags));
        self::assertSame(self::tagsToRows($lf->comments[0]->tags), self::tagsToRows($crlf->comments[0]->tags));
        self::assertSame(self::diagnosticsToRows($lf->warnings), self::diagnosticsToRows($crlf->warnings));
        self::assertSame(
            [
                [4, '"@bidirekt provides" is inside an @example, so it is not read: move it above the @example'],
                [8, '"@bidirekt response" is not at the start of a comment line, so it is not read: start the line with it'],
                [11, 'this comment is not above a statement or declaration, so its "@bidirekt" tags are not read: move it above the statement or declaration, before its attributes'],
            ],
            self::diagnosticsToRows($crlf->warnings),
        );
    }

    private function read(string $code): TaggedComments
    {
        file_put_contents($this->path, $code);
        $file = (new ParsedFiles())->pathToParsed($this->path);
        self::assertInstanceOf(ParsedFile::class, $file);

        return FilesToTaggedComments::filesToTaggedComments([$file]);
    }

    /**
     * @param list<CommentTag> $tags
     * @return list<array{string, list<string>, int}>
     */
    private static function tagsToRows(array $tags): array
    {
        return array_map(static fn(CommentTag $tag): array => [$tag->keyword, $tag->arguments, $tag->location->line ?? 0], $tags);
    }

    /**
     * @param list<Diagnostic> $diagnostics
     * @return list<array{int, string}>
     */
    private static function diagnosticsToRows(array $diagnostics): array
    {
        return array_map(static fn(Diagnostic $diagnostic): array => [$diagnostic->location->line ?? 0, $diagnostic->message], $diagnostics);
    }
}
