<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\PhpDocToWrittenType;
use Bidirekt\PhpSdk\Reader\WrittenType;
use PhpParser\ErrorHandler\Throwing;
use PhpParser\NameContext;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Use_;
use PHPUnit\Framework\TestCase;

final class PhpDocToWrittenTypeTest extends TestCase
{
    public function testPrefersTheVarThatNamesTheMember(): void
    {
        $doc = "/**\n * @var list<int>\n * @var list<string> \$tags\n * @var list<bool> \$flags\n */";

        $type = (new PhpDocToWrittenType())->docToVarType($doc, 'tags', self::globalNames(), new SourceLocation('pet.php', 3));

        self::assertInstanceOf(WrittenType::class, $type);
        self::assertSame('list<string>', $type->textOf($type->node));
    }

    public function testReadsTheParamOfTheMemberOnly(): void
    {
        $doc = "/**\n * @param list<int> \$scores\n * @param array<string,  Pet> \$pets\n */";

        $type = (new PhpDocToWrittenType())->docToParamType($doc, 'pets', self::globalNames(), new SourceLocation('pet.php', 3));

        self::assertInstanceOf(WrittenType::class, $type);
        self::assertSame('array<string, Pet>', $type->textOf($type->node));
    }

    public function testResolvesClassNamesThroughTheScope(): void
    {
        $names = new NameContext(new Throwing());
        $names->startNamespace(new Name('App\\Shelters'));
        $names->addAlias(new Name('App\\Pet'), 'P', Use_::TYPE_NORMAL);

        $type = (new PhpDocToWrittenType())->textToWrittenType('?P', $names);

        self::assertInstanceOf(WrittenType::class, $type);
        self::assertSame('App\\Pet', $type->classNameToFullyQualified('P'));
        self::assertSame('App\\Shelters\\Owner', $type->classNameToFullyQualified('Owner'));
    }

    public function testReportsAnUnreadableVar(): void
    {
        $location = new SourceLocation('pet.php', 3);

        $type = (new PhpDocToWrittenType())->docToVarType('/** @var list<int */', 'tags', self::globalNames(), $location);

        self::assertEquals(new Diagnostic('cannot read the @var of "tags": write a PHPDoc type, as in @var list<Pet>', $location), $type);
    }

    public function testReturnsNullWithoutADocblockOrATag(): void
    {
        $docs = new PhpDocToWrittenType();
        $location = new SourceLocation('pet.php', 3);

        self::assertNull($docs->docToVarType(null, 'tags', self::globalNames(), $location));
        self::assertNull($docs->docToVarType('/** Shown on the card */', 'tags', self::globalNames(), $location));
        self::assertNull($docs->docToParamType('/** @param int $age */', 'tags', self::globalNames(), $location));
        self::assertNull($docs->textToWrittenType('list<', self::globalNames()));
    }

    private static function globalNames(): NameContext
    {
        $names = new NameContext(new Throwing());
        $names->startNamespace();

        return $names;
    }
}
