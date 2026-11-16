<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Tests\Model;

use Bidirekt\PhpSdk\Model\Body;
use Bidirekt\PhpSdk\Model\ConsumedResource;
use Bidirekt\PhpSdk\Model\Diagnostic;
use Bidirekt\PhpSdk\Model\ProvidedResource;
use Bidirekt\PhpSdk\Model\ResourcesToMerged;
use Bidirekt\PhpSdk\Model\Response;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PHPUnit\Framework\TestCase;

final class ResourcesToMergedTest extends TestCase
{
    private const CLIENT = 'src/Client.php';

    public function testMergesTheStatusesOfHandlersThatConsumeTheSameResource(): void
    {
        $merged = ResourcesToMerged::consumedToMerged([
            self::charges(1, [self::response(201, 'Charge', 2), self::response(402, null, 3)]),
            self::charges(10, [self::response(201, 'Charge', 11), self::response(409, null, 12)]),
        ]);

        self::assertSame([], $merged->errors);
        self::assertEquals(
            [self::charges(1, [self::response(201, 'Charge', 2), self::response(402, null, 3), self::response(409, null, 12)])],
            $merged->resources,
        );
    }

    public function testKeepsResourcesOfDifferentProvidersApart(): void
    {
        $other = new ConsumedResource('billing_api', 'POST', '/charges', null, [], new SourceLocation(self::CLIENT, 10));

        $merged = ResourcesToMerged::consumedToMerged([self::charges(1, []), $other]);

        self::assertSame([], $merged->errors);
        self::assertEquals([self::charges(1, []), $other], $merged->resources);
    }

    public function testRejectsTheSameStatusWithDifferentSchemasAtBothPlaces(): void
    {
        $merged = ResourcesToMerged::consumedToMerged([
            self::charges(1, [self::response(201, 'Charge', 2)]),
            self::charges(10, [self::response(201, null, 11)]),
        ]);

        $message = 'response 201 of "POST /charges" consumed from "payments_api" is declared as "Charge" and null: declare one schema with every field the service reads';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation(self::CLIENT, 2)),
                new Diagnostic($message, new SourceLocation(self::CLIENT, 11)),
            ],
            $merged->errors,
        );
    }

    public function testRejectsRequestsWithDifferentSchemasAtBothPlaces(): void
    {
        $merged = ResourcesToMerged::consumedToMerged([
            self::charges(1, [], new Body('NewCharge', new SourceLocation(self::CLIENT, 2))),
            self::charges(10, [], new Body(null, new SourceLocation(self::CLIENT, 11))),
        ]);

        $message = 'request of "POST /charges" consumed from "payments_api" is declared as "NewCharge" and null: declare the same request in every handler';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation(self::CLIENT, 2)),
                new Diagnostic($message, new SourceLocation(self::CLIENT, 11)),
            ],
            $merged->errors,
        );
    }

    public function testRejectsARequestDeclaredInOneHandlerOnlyAtBothPlaces(): void
    {
        $merged = ResourcesToMerged::consumedToMerged([
            self::charges(1, []),
            self::charges(10, [], new Body('NewCharge', new SourceLocation(self::CLIENT, 11))),
        ]);

        $message = 'request of "POST /charges" consumed from "payments_api" is declared in one handler and not in another: declare the same request in every handler';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation(self::CLIENT, 1)),
                new Diagnostic($message, new SourceLocation(self::CLIENT, 11)),
            ],
            $merged->errors,
        );
    }

    public function testReportsAConflictRepeatedAtTheSamePlaceOnce(): void
    {
        $merged = ResourcesToMerged::consumedToMerged([
            self::charges(1, [self::response(201, 'Charge', 2)]),
            self::charges(10, [self::response(201, null, 11)]),
            self::charges(20, [self::response(201, null, 11)]),
        ]);

        $message = 'response 201 of "POST /charges" consumed from "payments_api" is declared as "Charge" and null: declare one schema with every field the service reads';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation(self::CLIENT, 2)),
                new Diagnostic($message, new SourceLocation(self::CLIENT, 11)),
            ],
            $merged->errors,
        );
    }

    public function testRejectsTheSameMethodAndPathInTwoHandlersAtBothPlaces(): void
    {
        $merged = ResourcesToMerged::providedToMerged([self::pets(1), self::pets(10)]);

        $message = '"GET /pets" is provided by more than one handler: declare each method and path in one handler';
        self::assertEquals(
            [
                new Diagnostic($message, new SourceLocation('src/Pets.php', 1)),
                new Diagnostic($message, new SourceLocation('src/Pets.php', 10)),
            ],
            $merged->errors,
        );
        self::assertEquals([self::pets(1)], $merged->resources);
    }

    private static function response(int $status, ?string $schema, int $line): Response
    {
        return new Response($status, new Body($schema, new SourceLocation(self::CLIENT, $line)));
    }

    /**
     * @param list<Response> $responses
     */
    private static function charges(int $line, array $responses, ?Body $request = null): ConsumedResource
    {
        return new ConsumedResource('payments_api', 'POST', '/charges', $request, $responses, new SourceLocation(self::CLIENT, $line));
    }

    private static function pets(int $line): ProvidedResource
    {
        return new ProvidedResource('GET', '/pets', null, [], new SourceLocation('src/Pets.php', $line));
    }
}
