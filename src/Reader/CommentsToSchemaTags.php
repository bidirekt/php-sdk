<?php

declare(strict_types=1);

namespace Bidirekt\PhpSdk\Reader;

use Bidirekt\PhpSdk\Model\Diagnostic;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassLike;

/**
 * Places the `@bidirekt schema` and `@bidirekt field` tags of the docblocks that declare no handler on
 * their elements: a schema on a class or an enum, a field on a property or a promoted parameter.
 */
final class CommentsToSchemaTags
{
    /**
     * @param list<TaggedComment> $comments
     */
    public static function commentsToSchemaTags(array $comments): SchemaTags
    {
        $read = new SchemaTags();
        foreach ($comments as $comment) {
            if ($comment->hasHandler()) {
                continue;
            }
            $schema = self::tagsToSingle($comment->tagsWith('schema'), $read);
            if ($schema !== null) {
                self::schemaTagToElement($schema, $comment, $read);
            }
            $field = self::tagsToSingle($comment->tagsWith('field'), $read);
            if ($field !== null) {
                self::fieldTagToElement($field, $comment, $read);
            }
        }

        return $read;
    }

    /**
     * @param list<CommentTag> $tags
     */
    private static function tagsToSingle(array $tags, SchemaTags $read): ?CommentTag
    {
        $tag = array_shift($tags);
        foreach ($tags as $extra) {
            $read->errors[] = new Diagnostic(sprintf('"@bidirekt %s" is declared more than once in this comment: keep one', $extra->keyword), $extra->location);
        }

        return $tag;
    }

    private static function schemaTagToElement(CommentTag $tag, TaggedComment $comment, SchemaTags $read): void
    {
        $element = $comment->element;
        if (!$element instanceof ClassLike) {
            $read->warnings[] = new Diagnostic(
                '"@bidirekt schema" is not above a class or an enum, so it is not read: put it above one of them',
                $tag->location,
            );

            return;
        }
        if ($read->schemaTag($comment->file->nodeToKey($element)) !== null) {
            $read->errors[] = new Diagnostic(
                sprintf('"@bidirekt schema" is declared more than once on %s: keep one', SchemaClasses::nodeToDescription($element)),
                $tag->location,
            );

            return;
        }
        $read->schemas[$comment->file->nodeToKey($element)] = $tag;
    }

    private static function fieldTagToElement(CommentTag $tag, TaggedComment $comment, SchemaTags $read): void
    {
        $element = $comment->element;
        if (!$element instanceof Stmt\Property && !$element instanceof Param) {
            $read->warnings[] = new Diagnostic(
                '"@bidirekt field" is not above a property, so it is not read: put it above a property or a promoted constructor parameter',
                $tag->location,
            );

            return;
        }
        if ($read->fieldTag($comment->file->nodeToKey($element)) !== null) {
            $read->errors[] = new Diagnostic(
                sprintf('"@bidirekt field" is declared more than once on property "%s": keep one', SchemaNaming::memberToName($element)),
                $tag->location,
            );

            return;
        }
        $read->fields[$comment->file->nodeToKey($element)] = $tag;
    }
}
