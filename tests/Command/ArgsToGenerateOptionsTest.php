<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Command;

use Bidirekt\PhpSdk\Command\ArgsToGenerateOptions;
use Bidirekt\PhpSdk\Command\GenerateOptions;
use PHPUnit\Framework\TestCase;

final class ArgsToGenerateOptionsTest extends TestCase
{
    public function testReadsGenerateAlone(): void
    {
        self::assertEquals(new GenerateOptions(), ArgsToGenerateOptions::argsToGenerateOptions(['generate']));
    }

    public function testReadsEveryOptionInBothForms(): void
    {
        self::assertEquals(
            new GenerateOptions(true, 'apps/api', 'out.yaml'),
            ArgsToGenerateOptions::argsToGenerateOptions(['--check', 'generate', '--project', 'apps/api', '--out=out.yaml']),
        );
        self::assertEquals(
            new GenerateOptions(false, '--odd', null),
            ArgsToGenerateOptions::argsToGenerateOptions(['generate', '--project', '--odd']),
        );
    }

    /**
     * @return array<string, array{list<string>}>
     */
    public function invalidArgs(): array
    {
        return [
            'no command' => [[]],
            'another command' => [['build']],
            'two positionals' => [['generate', 'now']],
            'an unknown option' => [['generate', '--watch']],
            'a value on --check' => [['generate', '--check=true']],
            'an option without its value' => [['generate', '--out']],
        ];
    }

    /**
     * @dataProvider invalidArgs
     * @param list<string> $args
     */
    public function testRejectsArgsOutsideTheUsage(array $args): void
    {
        self::assertNull(ArgsToGenerateOptions::argsToGenerateOptions($args));
    }
}
