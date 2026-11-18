<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    ->setRules(['@PER-CS' => true])
    ->setFinder(
        (new PhpCsFixer\Finder())
            ->in(__DIR__)
            ->exclude('tests/fixtures')
            ->exclude('tests/scenarios')
            ->append([__DIR__ . '/bin/php-sdk', __FILE__]),
    );
