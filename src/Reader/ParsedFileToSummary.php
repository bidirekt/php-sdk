<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\PropertyItem;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\TraitUse;

/**
 * Takes from a parsed file what the reader reads once every project file is read, so the file's tree is
 * dropped after it: the class-likes with their annotated members, constants and enum cases, the attributes
 * of its handlers, and its annotated properties and parameters.
 */
final class ParsedFileToSummary
{
    private const PROPERTY_DOCBLOCK = 'in the docblock above the property and its attributes';

    private const PARAMETER_DOCBLOCK = 'in the docblock above the parameter and its attributes';

    /** @var array<int, ClassSummary> node id => the summary of the class-like */
    private array $classes = [];

    /** @var array<int, list<SdkAttribute>> node id => its SDK attributes, read once for all their uses */
    private array $attributes = [];

    /** @var array<int, string> node id => its node key, made once for all its uses */
    private array $keys = [];

    private function __construct(
        private ParsedFile $file,
    ) {}

    /**
     * @param SchemaTags $tags the file's own `@bidirekt schema` and `@bidirekt field` tags
     */
    public static function parsedFileToSummary(ParsedFile $file, SchemaTags $tags): FileSummary
    {
        $reader = new self($file);
        $classes = [];
        foreach ($file->nodes->classLikes as $node) {
            $classes[] = $reader->classes[spl_object_id($node)] = $reader->classToSummary($node);
        }
        $handlers = [];
        foreach (HandlersToResources::fileToHandlers($file) as $function) {
            $attributes = $reader->nodeToAttributes($function);
            if ($attributes !== []) {
                $handlers[] = $attributes;
            }
        }
        $sites = [];
        foreach ($file->nodes->members as $member) {
            $site = $reader->memberToFieldSite($member, $tags);
            if ($site !== null) {
                $sites[] = $site;
            }
        }

        return new FileSummary($file->path, $classes, $handlers, $sites, $tags);
    }

    private function classToSummary(ClassLike $node): ClassSummary
    {
        $parent = null;
        $interfaces = [];
        if ($node instanceof Class_ && $node->extends !== null) {
            $parent = $this->nameToSummary($node->extends);
        }
        if ($node instanceof Class_ || $node instanceof Enum_) {
            $interfaces = array_map(fn(Name $name): NameSummary => $this->nameToSummary($name), $node->implements);
        }
        if ($node instanceof Interface_) {
            $interfaces = array_map(fn(Name $name): NameSummary => $this->nameToSummary($name), $node->extends);
        }
        $scalarType = null;
        if ($node instanceof Enum_ && $node->scalarType !== null) {
            $scalarType = $node->scalarType->toLowerString();
        }
        $members = [];
        $constants = [];
        $cases = [];
        foreach ($node->stmts as $statement) {
            if ($statement instanceof TraitUse) {
                $members[] = new TraitUseSummary(array_values(array_map(fn(Name $name): NameSummary => $this->nameToSummary($name), $statement->traits)));
            }
            if ($statement instanceof Stmt\Property) {
                $members[] = $this->propertyToDeclaration($statement);
            }
            if ($statement instanceof ClassMethod && $statement->name->toLowerString() === '__construct') {
                array_push($members, ...$this->constructorToDeclarations($statement));
            }
            if ($statement instanceof ClassConst) {
                foreach ($statement->consts as $const) {
                    $constants[$const->name->toString()] ??= ExpressionToSummary::expressionToSummary($const->value, $this->file);
                }
            }
            if ($statement instanceof EnumCase) {
                $value = null;
                if ($statement->expr !== null) {
                    $value = ExpressionToSummary::expressionToSummary($statement->expr, $this->file);
                }
                $cases[] = new EnumCaseSummary($statement->name->toString(), $value);
            }
        }

        return new ClassSummary(
            $this->file->nodeToKey($node),
            $node->namespacedName?->toString(),
            SchemaClasses::nodeToKind($node),
            SchemaClasses::nodeToDescription($node),
            $this->file->locationOf($node),
            $parent,
            array_values($interfaces),
            $scalarType,
            $this->nodeToAttributes($node),
            $members,
            $constants,
            $cases,
        );
    }

    private function propertyToDeclaration(Stmt\Property $statement): MemberDeclaration
    {
        $doc = $statement->getDocComment()?->getText();
        $mentionsSpecialClass = self::mentionsSpecialClass($this->file->textOf($statement) . $doc);
        $members = [];
        foreach ($statement->props as $item) {
            $members[] = $this->itemToSummary($item, $statement, $doc, $mentionsSpecialClass);
        }

        return new MemberDeclaration(
            $this->nodeToKey($statement),
            $this->nodeToAttributes($statement),
            $statement->isStatic(),
            $members,
        );
    }

    private function itemToSummary(PropertyItem $item, Stmt\Property $statement, ?string $doc, bool $mentionsSpecialClass): MemberSummary
    {
        $name = $item->name->toString();
        $source = new FieldSource($name, $this->typeToText($statement->type), $doc, null, NameContextVisitor::nodeToNameContext($item), self::PROPERTY_DOCBLOCK);

        return new MemberSummary($name, $this->file->locationOf($item), $source, $mentionsSpecialClass);
    }

    /**
     * @return list<MemberDeclaration> its promoted parameters
     */
    private function constructorToDeclarations(ClassMethod $constructor): array
    {
        $constructorDoc = $constructor->getDocComment()?->getText();
        $declarations = [];
        foreach ($constructor->params as $param) {
            if (!$param->isPromoted() || !$param->var instanceof Variable || !is_string($param->var->name)) {
                continue;
            }
            $name = $param->var->name;
            $doc = $param->getDocComment()?->getText();
            $source = new FieldSource($name, $this->typeToText($param->type), $doc, $constructorDoc, NameContextVisitor::nodeToNameContext($param), self::PARAMETER_DOCBLOCK);
            $key = $this->nodeToKey($param);
            $mentionsSpecialClass = self::mentionsSpecialClass($this->file->textOf($param) . $doc . $constructorDoc);
            $declarations[] = new MemberDeclaration(
                $key,
                $this->nodeToAttributes($param),
                false,
                [new MemberSummary($name, $this->file->locationOf($param), $source, $mentionsSpecialClass)],
            );
        }

        return $declarations;
    }

    private function memberToFieldSite(Stmt\Property|Param $member, SchemaTags $tags): ?FieldSite
    {
        $attributes = $this->nodeToAttributes($member);
        $key = $this->nodeToKey($member);
        if (!self::hasFieldAttribute($attributes) && $tags->fieldTag($key) === null) {
            return null;
        }
        $enclosing = NodeToEnclosingClass::nodeToEnclosingClass($member);
        $owner = null;
        if ($enclosing !== null) {
            $owner = $this->classes[spl_object_id($enclosing)];
        }

        return new FieldSite($key, $attributes, $member instanceof Param && !$member->isPromoted(), $owner);
    }

    /**
     * @return list<SdkAttribute>
     */
    private function nodeToAttributes(Node $node): array
    {
        return $this->attributes[spl_object_id($node)] ??= SdkAttributes::elementToSdkAttributes($node, $this->file);
    }

    private function nodeToKey(Node $node): string
    {
        return $this->keys[spl_object_id($node)] ??= $this->file->nodeToKey($node);
    }

    /**
     * @param list<SdkAttribute> $attributes
     */
    private static function hasFieldAttribute(array $attributes): bool
    {
        foreach ($attributes as $attribute) {
            if ($attribute->name === 'BidirektField') {
                return true;
            }
        }

        return false;
    }

    private function nameToSummary(Name $name): NameSummary
    {
        $written = $name->getAttribute('originalName');
        if (!$written instanceof Name) {
            $written = $name;
        }

        return new NameSummary($name->toString(), $written->toString(), $this->file->locationOf($name));
    }

    private function typeToText(?Node $type): ?string
    {
        if ($type === null) {
            return null;
        }

        return $this->file->textOf($type);
    }

    private static function mentionsSpecialClass(string $written): bool
    {
        return preg_match('/\b(?:self|static|parent)\b/i', $written) === 1;
    }
}
