<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\NameContext;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\ParserException;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;

/**
 * Parses types with the phpdoc-parser: a native type's text, and the `@var` or `@param` of a docblock.
 */
final class PhpDocToWrittenType
{
    private Lexer $lexer;

    private TypeParser $typeParser;

    private PhpDocParser $docParser;

    public function __construct()
    {
        $config = new ParserConfig(['indexes' => true]);
        $this->lexer = new Lexer($config);
        $constants = new ConstExprParser($config);
        $this->typeParser = new TypeParser($config, $constants);
        $this->docParser = new PhpDocParser($config, $this->typeParser, $constants);
    }

    public function textToWrittenType(string $text, NameContext $names): ?WrittenType
    {
        $tokens = $this->lexer->tokenize($text);
        $iterator = new TokenIterator($tokens);
        try {
            $node = $this->typeParser->parse($iterator);
            $iterator->consumeTokenType(Lexer::TOKEN_END);
        } catch (ParserException) {
            return null;
        }

        return new WrittenType($node, $tokens, $names, true);
    }

    /**
     * The type of the `@var` that describes the member: one naming its variable, or else one naming none.
     */
    public function docToVarType(?string $doc, string $member, NameContext $names, SourceLocation $location): WrittenType|Diagnostic|null
    {
        if ($doc === null) {
            return null;
        }
        $tokens = $this->lexer->tokenize($doc);
        $found = null;
        foreach ($this->docParser->parse(new TokenIterator($tokens))->getTagsByName('@var') as $tag) {
            if ($tag->value instanceof InvalidTagValueNode) {
                return new Diagnostic(sprintf('cannot read the @var of "%s": write a PHPDoc type, as in @var list<Pet>', $member), $location);
            }
            if (!$tag->value instanceof VarTagValueNode || !in_array($tag->value->variableName, ['', '$' . $member], true)) {
                continue;
            }
            if ($found === null || $tag->value->variableName !== '') {
                $found = $tag->value;
            }
        }
        if ($found === null) {
            return null;
        }

        return new WrittenType($found->type, $tokens, $names);
    }

    public function docToParamType(?string $doc, string $member, NameContext $names, SourceLocation $location): WrittenType|Diagnostic|null
    {
        if ($doc === null) {
            return null;
        }
        $tokens = $this->lexer->tokenize($doc);
        foreach ($this->docParser->parse(new TokenIterator($tokens))->getTagsByName('@param') as $tag) {
            if ($tag->value instanceof InvalidTagValueNode && preg_match('/\$' . preg_quote($member, '/') . '\b/', $tag->value->value) === 1) {
                return new Diagnostic(sprintf('cannot read the @param of "%s": write a PHPDoc type, as in @param list<Pet> $%s', $member, $member), $location);
            }
            if ($tag->value instanceof ParamTagValueNode && $tag->value->parameterName === '$' . $member) {
                return new WrittenType($tag->value->type, $tokens, $names);
            }
        }

        return null;
    }
}
