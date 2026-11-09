<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Analyses\PhpDoc;
use DressCode\Decision;
use DressCode\Domains\Words;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use DressCode\Violation;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocChildNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PhpSyntax\Node;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Finishing, modifiesComments: true, analyses: [PhpDoc::class])]
final class ThrowsAnnotationsOrderRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.throwsAnnotationsOrder',
                new Words(['alphabetical' => 'sorted by their written exception types regardless of letter case']),
                'The order of the `@throws` annotations of a function, a method or a closure, '
                . 'by the first exception type of each'
            ),
        ];
    }

    /** @return list<PhpDocTagNode> */
    private static function findThrowsAnnotations(PhpDocNode $phpDoc): array
    {
        return array_values(array_filter(
            $phpDoc->children,
            static fn (PhpDocChildNode $child) => $child instanceof PhpDocTagNode
                && $child->name === '@throws'
                && $child->value instanceof ThrowsTagValueNode
        ));
    }

    private static function getType(PhpDocTagNode $annotation): string
    {
        $type = $annotation->value instanceof ThrowsTagValueNode ? $annotation->value->type : null;

        return (string) ($type instanceof UnionTypeNode ? $type->types[0] : $type);
    }

    /**
     * The fix moves whole lines, which would carry along the opening or the closing of the doc comment.
     *
     * @param list<PhpDocTagNode> $annotations
     */
    private static function haveOwnLines(array $annotations, int $lineCount): bool
    {
        return $annotations[0]->getAttribute(Attribute::START_LINE) > 1
            && $annotations[count($annotations) - 1]->getAttribute(Attribute::END_LINE) < $lineCount;
    }

    /**
     * @param list<string> $lines
     * @param list<PhpDocTagNode> $annotations
     * @param list<PhpDocTagNode> $sorted
     * @return list<string>
     */
    private static function reorder(array $lines, array $annotations, array $sorted): array
    {
        $reordered = $lines;

        foreach (array_reverse($annotations, true) as $i => $annotation) {
            [$offset, $length] = self::getLineRange($annotation);
            array_splice($reordered, $offset, $length, array_slice($lines, ...self::getLineRange($sorted[$i])));
        }

        return $reordered;
    }

    /** @return array{int, int} the offset and the count of the lines of the annotation */
    private static function getLineRange(PhpDocTagNode $annotation): array
    {
        $start = $annotation->getAttribute(Attribute::START_LINE);

        return [$start - 1, $annotation->getAttribute(Attribute::END_LINE) - $start + 1];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [FunctionLikeNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        $docComment = $node instanceof FunctionLikeNode ? $node->getDocComment() : null;

        if ($docComment === null) {
            return;
        }

        $annotations = self::findThrowsAnnotations($context->getAnalysis(PhpDoc::class)->parse($docComment));
        $sorted = $annotations;
        usort(
            $sorted,
            static fn (PhpDocTagNode $a, PhpDocTagNode $b) => strcasecmp(
                ltrim(self::getType($a), '\\'),
                ltrim(self::getType($b), '\\')
            )
        );

        if ($sorted === $annotations) {
            return;
        }

        $eol = str_contains($docComment->text, "\r\n") ? "\r\n" : "\n";
        $lines = explode("\n", str_replace("\r\n", "\n", $docComment->text));
        $types = array_map(static fn (PhpDocTagNode $tag) => Violation::formatCode(self::getType($tag)), $sorted);

        if (!$context->report(
            $node,
            'The `@throws` annotations must be in alphabetical order: ' . implode(', ', $types) . '.',
            trivia: $docComment,
            fixable: self::haveOwnLines($annotations, count($lines))
        )) {
            return;
        }

        $node->replaceDocComment($docComment->withText(implode($eol, self::reorder($lines, $annotations, $sorted))));
    }
}
