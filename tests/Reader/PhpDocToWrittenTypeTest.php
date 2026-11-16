<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\NameScope;
use Bidirekt\PhpSdk\Reader\PhpDocToWrittenType;
use Bidirekt\PhpSdk\Reader\WrittenType;
use PHPUnit\Framework\TestCase;

final class PhpDocToWrittenTypeTest extends TestCase
{
    public function testPrefersTheVarThatNamesTheMember(): void
    {
        $doc = "/**\n * @var list<int>\n * @var list<string> \$tags\n * @var list<bool> \$flags\n */";

        $type = (new PhpDocToWrittenType())->docToVarType($doc, 'tags', new NameScope(''), new SourceLocation('pet.php', 3));

        self::assertInstanceOf(WrittenType::class, $type);
        self::assertSame('list<string>', $type->textOf($type->node));
    }

    public function testReadsTheParamOfTheMemberOnly(): void
    {
        $doc = "/**\n * @param list<int> \$scores\n * @param array<string,  Pet> \$pets\n */";

        $type = (new PhpDocToWrittenType())->docToParamType($doc, 'pets', new NameScope(''), new SourceLocation('pet.php', 3));

        self::assertInstanceOf(WrittenType::class, $type);
        self::assertSame('array<string, Pet>', $type->textOf($type->node));
    }

    public function testResolvesClassNamesThroughTheScope(): void
    {
        $scope = new NameScope('App\\Shelters');
        $scope->addAlias('P', 'App\\Pet');

        $type = (new PhpDocToWrittenType())->textToWrittenType('?P', $scope);

        self::assertInstanceOf(WrittenType::class, $type);
        self::assertSame('App\\Pet', $type->classNameToFullyQualified('P'));
        self::assertSame('App\\Shelters\\Owner', $type->classNameToFullyQualified('Owner'));
    }

    public function testReportsAnUnreadableVar(): void
    {
        $location = new SourceLocation('pet.php', 3);

        $type = (new PhpDocToWrittenType())->docToVarType('/** @var list<int */', 'tags', new NameScope(''), $location);

        self::assertEquals(new Diagnostic('cannot read the @var of "tags": write a PHPDoc type, as in @var list<Pet>', $location), $type);
    }

    public function testReturnsNullWithoutADocblockOrATag(): void
    {
        $docs = new PhpDocToWrittenType();
        $location = new SourceLocation('pet.php', 3);

        self::assertNull($docs->docToVarType(null, 'tags', new NameScope(''), $location));
        self::assertNull($docs->docToVarType('/** Shown on the card */', 'tags', new NameScope(''), $location));
        self::assertNull($docs->docToParamType('/** @param int $age */', 'tags', new NameScope(''), $location));
        self::assertNull($docs->textToWrittenType('list<', new NameScope('')));
    }
}
