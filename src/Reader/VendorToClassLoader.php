<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use Composer\Autoload\ClassLoader;
use Bidirekt\PhpSdk\Model\SourceLocation;
use PhpParser\ConstExprEvaluationException;
use PhpParser\ConstExprEvaluator;
use PhpParser\Error;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\MagicConst\Dir;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;

/**
 * Reads the maps Composer generates in `vendor/composer/` as data into a class loader that is never
 * registered, only asked for files: the maps are parsed and their `$vendorDir . '…'` / `$baseDir . '…'`
 * expressions evaluated, never included.
 */
final class VendorToClassLoader
{
    /**
     * @return ClassLoader|Diagnostic|null null when the vendor folder is not installed
     */
    public static function vendorToClassLoader(string $vendorDirectory): ClassLoader|Diagnostic|null
    {
        $composerDirectory = $vendorDirectory . '/composer';
        if (!is_dir($composerDirectory)) {
            return null;
        }
        $loader = new ClassLoader();
        $psr4 = self::mapFileToArray($composerDirectory . '/autoload_psr4.php');
        if ($psr4 instanceof Diagnostic) {
            return $psr4;
        }
        foreach (self::arrayToDirectories($psr4) as $prefix => $directories) {
            if ($prefix !== '' && !str_ends_with($prefix, '\\')) {
                return self::unreadable($composerDirectory . '/autoload_psr4.php');
            }
            $loader->setPsr4($prefix, $directories);
        }
        $psr0 = self::mapFileToArray($composerDirectory . '/autoload_namespaces.php');
        if ($psr0 instanceof Diagnostic) {
            return $psr0;
        }
        foreach (self::arrayToDirectories($psr0) as $prefix => $directories) {
            $loader->set($prefix, $directories);
        }
        $classmap = self::mapFileToArray($composerDirectory . '/autoload_classmap.php');
        if ($classmap instanceof Diagnostic) {
            return $classmap;
        }
        // A classmap entry whose file is missing is left out, so the class is looked up by PSR-4 and PSR-0.
        $loader->addClassMap(array_filter(self::arrayToFiles($classmap), 'is_file'));

        return $loader;
    }

    /**
     * @return array<mixed>|Diagnostic
     */
    private static function mapFileToArray(string $file): array|Diagnostic
    {
        if (!is_file($file)) {
            return [];
        }
        $unreadable = self::unreadable($file);
        try {
            $statements = (new ParserFactory())->createForNewestSupportedVersion()->parse((string) file_get_contents($file)) ?? [];
            $variables = [];
            foreach ($statements as $statement) {
                if ($statement instanceof Expression && $statement->expr instanceof Assign && $statement->expr->var instanceof Variable && is_string($statement->expr->var->name)) {
                    $variables[$statement->expr->var->name] = self::expressionToValue($statement->expr->expr, $variables, dirname($file));
                }
                if ($statement instanceof Return_ && $statement->expr !== null) {
                    $value = self::expressionToValue($statement->expr, $variables, dirname($file));
                    if (!is_array($value)) {
                        return $unreadable;
                    }

                    return $value;
                }
            }
        } catch (Error|ConstExprEvaluationException) {
            return $unreadable;
        }

        return $unreadable;
    }

    private static function unreadable(string $file): Diagnostic
    {
        return new Diagnostic('cannot read the Composer autoload map: run composer install again', new SourceLocation($file, 1));
    }

    /**
     * @param array<string, mixed> $variables
     */
    private static function expressionToValue(Expr $expression, array $variables, string $directory): mixed
    {
        $evaluator = new ConstExprEvaluator(static function (Expr $expression) use ($variables, $directory): mixed {
            if ($expression instanceof Variable && is_string($expression->name) && array_key_exists($expression->name, $variables)) {
                return $variables[$expression->name];
            }
            if ($expression instanceof Dir) {
                return $directory;
            }
            if ($expression instanceof FuncCall && $expression->name instanceof Name && $expression->name->toLowerString() === 'dirname') {
                return self::dirnameCallToValue($expression, $variables, $directory);
            }
            throw new ConstExprEvaluationException('unsupported expression');
        });

        return $evaluator->evaluateDirectly($expression);
    }

    /**
     * @param array<string, mixed> $variables
     */
    private static function dirnameCallToValue(FuncCall $call, array $variables, string $directory): string
    {
        $arguments = $call->getArgs();
        $path = self::expressionToValue($arguments[0]->value ?? throw new ConstExprEvaluationException('dirname without a path'), $variables, $directory);
        $levels = 1;
        if (isset($arguments[1]) && $arguments[1]->value instanceof Int_) {
            $levels = $arguments[1]->value->value;
        }
        if (!is_string($path) || $levels < 1) {
            throw new ConstExprEvaluationException('dirname of a non-string');
        }

        return dirname($path, $levels);
    }

    /**
     * @param array<mixed> $map
     * @return array<string, list<string>>
     */
    private static function arrayToDirectories(array $map): array
    {
        $directories = [];
        foreach ($map as $prefix => $paths) {
            if (!is_array($paths)) {
                continue;
            }
            $directories[(string) $prefix] = array_values(array_filter($paths, 'is_string'));
        }

        return $directories;
    }

    /**
     * @param array<mixed> $map
     * @return array<string, string>
     */
    private static function arrayToFiles(array $map): array
    {
        $files = [];
        foreach ($map as $class => $file) {
            if (is_string($file)) {
                $files[(string) $class] = $file;
            }
        }

        return $files;
    }
}
