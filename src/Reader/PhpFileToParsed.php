<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use RangeException;

final class PhpFileToParsed
{
    private Parser $parser;

    public function __construct(?Parser $parser = null)
    {
        $this->parser = $parser ?? (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Every attribute, `use` and tag the SDK reads writes "bidirekt", so only a project file that does is parsed;
     * any other is kept with the names it may declare a class under, and read when one of them is looked up.
     */
    public function projectCodeToRead(string $path, string $code): ParsedFile|UnparsedFile|Diagnostic
    {
        if (stripos($code, 'bidirekt') !== false) {
            return $this->codeToParsed($path, $code);
        }

        return new UnparsedFile($path, CodeToDeclaredClasses::codeToCandidateNames($code));
    }

    public function codeToParsed(string $path, string $code): ParsedFile|Diagnostic
    {
        $statements = $this->codeToStatements($path, $code);
        if ($statements instanceof Diagnostic) {
            return $statements;
        }
        $nodes = new FileNodesVisitor($code);
        $resolver = new NameResolver(new Collecting(), ['preserveOriginalNames' => true, 'replaceNodes' => true]);
        $traverser = new NodeTraverser($resolver, new NameContextVisitor($resolver), $nodes);
        $resolved = array_values($traverser->traverse($statements));

        return new ParsedFile($path, $code, $resolved, array_values($this->parser->getTokens()), $nodes->nodes);
    }

    /**
     * @return list<Node>|Diagnostic
     */
    private function codeToStatements(string $path, string $code): array|Diagnostic
    {
        try {
            return array_values($this->parser->parse($code) ?? []);
        } catch (Error $error) {
            return new Diagnostic(
                'syntax error: ' . self::parserMessageToText($error->getRawMessage()) . ': fix the code so PHP can parse it',
                new SourceLocation($path, max(1, $error->getStartLine())),
            );
        } catch (RangeException) {
            // PHP-Parser throws it for a token of a PHP version newer than it reads.
            return new Diagnostic(
                sprintf('cannot parse the file: nikic/php-parser does not read the syntax of PHP %d.%d yet: update it with composer update nikic/php-parser', PHP_MAJOR_VERSION, PHP_MINOR_VERSION),
                new SourceLocation($path, 1),
            );
        }
    }

    public static function fileToCode(string $path): string|Diagnostic
    {
        $code = @file_get_contents($path);
        if ($code === false) {
            return new Diagnostic('cannot read the file: check that it exists and can be read', new SourceLocation($path, 1));
        }

        return $code;
    }

    private static function parserMessageToText(string $message): string
    {
        $text = rtrim($message, '.');
        if (str_starts_with($text, 'Syntax error, ')) {
            $text = substr($text, strlen('Syntax error, '));
        }

        return lcfirst($text);
    }
}
