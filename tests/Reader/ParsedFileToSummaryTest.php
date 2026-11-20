<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Reader\ClassConstantSummary;
use Bidirekt\PhpSdk\Reader\MemberDeclaration;
use Bidirekt\PhpSdk\Reader\ParsedFile;
use Bidirekt\PhpSdk\Reader\ParsedFileToSummary;
use Bidirekt\PhpSdk\Reader\PhpFileToParsed;
use Bidirekt\PhpSdk\Reader\SchemaTags;
use Bidirekt\PhpSdk\Reader\TraitUseSummary;
use PhpParser\Node\Name;
use PHPUnit\Framework\TestCase;
use WeakReference;

final class ParsedFileToSummaryTest extends TestCase
{
    private const FILE = '/project/pet.php';

    private const CODE = <<<'PHP'
        <?php

        namespace App;

        use Bidirekt\PhpSdk\BidirektField;
        use Bidirekt\PhpSdk\BidirektSchema;
        use App\Models\{Owner, Tag as Label};

        #[BidirektSchema]
        final class Pet extends Animal
        {
            use Named;

            public const KIND = self::class;

            /** @var list<Label> */
            #[BidirektField(description: 'The tags')]
            public array $tags;

            public function __construct(
                #[BidirektField]
                public Owner $owner,
            ) {}
        }
        PHP;

    public function testKeepsTheClassesWithTheirMembersInTheOrderTheyAreWritten(): void
    {
        $parsed = (new PhpFileToParsed())->codeToParsed(self::FILE, self::CODE);
        self::assertInstanceOf(ParsedFile::class, $parsed);

        $summary = ParsedFileToSummary::parsedFileToSummary($parsed, new SchemaTags());

        $pet = $summary->classes[0];
        self::assertSame('App\\Pet', $pet->name);
        self::assertSame('App\\Animal', $pet->parent?->resolved);
        self::assertSame(['BidirektSchema'], array_map(static fn($attribute): string => $attribute->name, $pet->attributes));
        self::assertInstanceOf(TraitUseSummary::class, $pet->members[0]);
        self::assertSame('App\\Named', $pet->members[0]->traits[0]->resolved);
        self::assertInstanceOf(MemberDeclaration::class, $pet->members[1]);
        self::assertSame('tags', $pet->members[1]->members[0]->name);
        self::assertSame(18, $pet->members[1]->members[0]->location->line);
        self::assertInstanceOf(MemberDeclaration::class, $pet->members[2]);
        self::assertSame('Owner', $pet->members[2]->members[0]->source->nativeType);
        self::assertInstanceOf(ClassConstantSummary::class, $pet->constants['KIND']);
        self::assertSame('App\\Pet', $pet->constants['KIND']->enclosingClass);
    }

    public function testKeepsNothingOfTheTreeSoItIsFreedOnceTheFileIsRead(): void
    {
        $parsed = (new PhpFileToParsed())->codeToParsed(self::FILE, self::CODE);
        self::assertInstanceOf(ParsedFile::class, $parsed);
        $tree = WeakReference::create($parsed->statements[0]);

        $summary = ParsedFileToSummary::parsedFileToSummary($parsed, new SchemaTags());
        unset($parsed);
        gc_collect_cycles();

        self::assertNull($tree->get());
        $tags = $summary->classes[0]->members[1];
        self::assertInstanceOf(MemberDeclaration::class, $tags);
        self::assertSame('App\\Models\\Tag', $tags->members[0]->source->names->getResolvedClassName(new Name('Label'))->toString());
    }
}
