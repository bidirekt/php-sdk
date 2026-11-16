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
        $code = self::fileToCode($path);
        if ($code instanceof Diagnostic) {
            return $code;
        }

        return $this->codeToParsed($path, $code);
    }

    /**
     * Every attribute, `use` and tag the SDK reads writes "bidirekt", so only a project file that does is kept
     * whole; any other is parsed for its syntax errors and the classes it declares, read again on lookup.
     *
     * @return ParsedFile|list<DeclaredClass>|Diagnostic
     */
    public function projectFileToRead(string $path): ParsedFile|array|Diagnostic
    {
        $code = self::fileToCode($path);
        if ($code instanceof Diagnostic) {
            return $code;
        }
        if (stripos($code, 'bidirekt') !== false) {
            return $this->codeToParsed($path, $code);
        }
        $statements = $this->codeToStatements($path, $code);
        if ($statements instanceof Diagnostic) {
            return $statements;
        }
        $resolved = (new NodeTraverser(new NameResolver(new Collecting(), ['replaceNodes' => false])))->traverse($statements);
        $declared = [];
        $file = new ParsedFile($path, array_values($resolved), []);
        foreach ($file->classDeclarations() as $declaration) {
            $declared[] = new DeclaredClass($declaration->name, $file->locationOf($declaration->node));
        }

        return $declared;
    }

    private function codeToParsed(string $path, string $code): ParsedFile|Diagnostic
    {
        $statements = $this->codeToStatements($path, $code);
        if ($statements instanceof Diagnostic) {
            return $statements;
        }
        $resolver = new NameResolver(new Collecting(), ['preserveOriginalNames' => true, 'replaceNodes' => true]);
        $traverser = new NodeTraverser($resolver, new NameContextVisitor($resolver), new ParentConnectingVisitor());

        return new ParsedFile($path, array_values($traverser->traverse($statements)), array_values($this->parser->getTokens()));
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
        }
    }

    private static function fileToCode(string $path): string|Diagnostic
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
