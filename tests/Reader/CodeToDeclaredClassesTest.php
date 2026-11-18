<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Reader;

use Bidirekt\PhpSdk\Model\SourceLocation;
use Bidirekt\PhpSdk\Reader\CodeToDeclaredClasses;
use Bidirekt\PhpSdk\Reader\DeclaredClass;
use PHPUnit\Framework\TestCase;

final class CodeToDeclaredClassesTest extends TestCase
{
    private const FILE = '/project/pet.php';

    public function testReadsEveryNamedClassLikeWithItsNamespaceAndTheLineOfItsName(): void
    {
        $code = <<<'PHP'
            <?php

            namespace App\Model;

            #[Entity]
            final class Pet {}

            interface Named {}

            trait Timestamped {}

            ENUM Status: string
            {
                case Sold = 'sold';
            }
            PHP;

        self::assertEquals([
            new DeclaredClass('App\\Model\\Pet', new SourceLocation(self::FILE, 6)),
            new DeclaredClass('App\\Model\\Named', new SourceLocation(self::FILE, 8)),
            new DeclaredClass('App\\Model\\Timestamped', new SourceLocation(self::FILE, 10)),
            new DeclaredClass('App\\Model\\Status', new SourceLocation(self::FILE, 12)),
        ], CodeToDeclaredClasses::codeToDeclaredClasses(self::FILE, $code));
    }

    public function testFollowsEachNamespaceOfTheFile(): void
    {
        $code = <<<'PHP'
            <?php

            namespace App {
                class Pet {}
            }

            namespace {
                class Owner {}
            }
            PHP;

        self::assertEquals([
            new DeclaredClass('App\\Pet', new SourceLocation(self::FILE, 4)),
            new DeclaredClass('Owner', new SourceLocation(self::FILE, 8)),
        ], CodeToDeclaredClasses::codeToDeclaredClasses(self::FILE, $code));
    }

    public function testTakesNoNameInCommentsStringsHeredocsOrHtmlAndNoAnonymousClassForADeclaration(): void
    {
        $code = <<<'PHP'
            <p>class Html</p>
            <?php

            // the class Comment
            /** The enum Doc */
            $text = 'class Single';
            $more = "interface Double";
            $heredoc = <<<TEXT
                trait Heredoc
                TEXT;
            $name = Pet::class;
            $anonymous = new class extends Base {};
            $enum = enum($text);
            PHP;

        self::assertSame([], CodeToDeclaredClasses::codeToDeclaredClasses(self::FILE, $code));
    }

    public function testFindsTheNameOfEveryDeclarationInTheTextEvenAfterAComment(): void
    {
        $code = <<<'PHP'
            <?php

            namespace App;

            final CLASS /* a pet */ Pet {}
            interface // named
                Named {}
            enum Status: string {}
            // the class Owner is elsewhere
            $name = Pet::class;
            PHP;

        self::assertSame(['pet', 'named', 'status', 'owner'], CodeToDeclaredClasses::codeToCandidateNames($code));
    }

    public function testFindsTheNameOfADeclarationBelowACommentEndingInAKeyword(): void
    {
        $code = <<<'PHP'
            <?php

            // The status of a pet, sent as a string enum
            enum Status: string {}
            // One value per animal class
            #[Deprecated]
            enum Kind: string {}
            PHP;

        self::assertSame(['enum', 'status', 'kind'], CodeToDeclaredClasses::codeToCandidateNames($code));
    }

    public function testFindsTheNamesAfterABannerOfSlashesOrHashesBelowAKeyword(): void
    {
        $code = <<<'PHP'
            <?php

            final class Labels
            {
                // One label per enum
                ////////////////////////////////////////////////////////////////////////////
                # One label per class
                ############################################################################
            }

            enum Status: string {}
            PHP;

        self::assertSame(['labels', 'status'], CodeToDeclaredClasses::codeToCandidateNames($code));
    }

    public function testFallsBackToEveryWordOfTheCodeWhenThePatternExceedsPcreLimits(): void
    {
        $code = <<<'PHP'
            <?php

            final class Pet {}
            enum Status: string {}
            PHP;
        $limit = (string) ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');
        try {
            $names = CodeToDeclaredClasses::codeToCandidateNames($code);
        } finally {
            ini_set('pcre.backtrack_limit', $limit);
        }

        self::assertSame(['php', 'final', 'class', 'pet', 'enum', 'status', 'string'], $names);
    }
}
