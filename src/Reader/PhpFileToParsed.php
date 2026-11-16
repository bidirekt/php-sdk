<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\Error;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\Parser;
use PhpParser\ParserFactory;

final class PhpFileToParsed
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    public function fileToParsed(string $path): ParsedFile|Diagnostic
    {
        $code = @file_get_contents($path);
        if ($code === false) {
            return new Diagnostic('cannot read the file: check that it exists and can be read', new SourceLocation($path, 1));
        }
        try {
            $statements = $this->parser->parse($code) ?? [];
        } catch (Error $error) {
            return new Diagnostic(
                'syntax error: ' . self::parserMessageToText($error->getRawMessage()) . ': fix the code so PHP can parse it',
                new SourceLocation($path, max(1, $error->getStartLine())),
            );
        }
        $traverser = new NodeTraverser(
            new NameResolver(new Collecting(), ['preserveOriginalNames' => true, 'replaceNodes' => true]),
            new NameScopeVisitor(),
            new ParentConnectingVisitor(),
        );

        return new ParsedFile($path, array_values($traverser->traverse($statements)), array_values($this->parser->getTokens()));
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
