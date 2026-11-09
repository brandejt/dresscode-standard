<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Analyses\PhpDoc;
use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PhpSyntax\Node;
use PhpSyntax\Token;

#[RuleInfo(Stage::Finishing, modifiesComments: true, analyses: [PhpDoc::class])]
final class PhpdocAnnotationsApartRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.phpdocAnnotationsApart',
                Domain::state('required'),
                'A blank line between the description and the annotations of a doc comment, '
                . 'and none between two annotations'
            ),
        ];
    }

    /**
     * @return list<array{end: int, start: int, blankLines: int}> the line before each gap with a wrong count of blank
     *   lines and the annotation line after it, with the count expected, top down
     */
    private static function findWrongGaps(PhpDocNode $phpDoc): array
    {
        $gaps = [];
        $previous = null;

        foreach ($phpDoc->children as $child) {
            if ($child instanceof PhpDocTextNode && trim($child->text) === '') {
                continue;
            }

            if ($child instanceof PhpDocTagNode && $previous !== null) {
                $gap = [
                    'end' => $previous->getAttribute(Attribute::END_LINE),
                    'start' => $child->getAttribute(Attribute::START_LINE),
                    'blankLines' => $previous instanceof PhpDocTagNode ? 0 : 1,
                ];

                if ($gap['start'] > $gap['end'] && $gap['start'] - $gap['end'] - 1 !== $gap['blankLines']) {
                    $gaps[] = $gap;
                }
            }

            $previous = $child;
        }

        return $gaps;
    }

    private static function createBlankLine(string $annotationLine): string
    {
        return preg_match('~^[ \t]*\*~', $annotationLine, $match) === 1 ? $match[0] : '';
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [Token::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof Token || !$node->hasComment()) {
            return;
        }

        foreach ($node->getDocComments() as $trivia) {
            $gaps = $trivia->isMultiLineComment()
                ? self::findWrongGaps($context->getAnalysis(PhpDoc::class)->parse($trivia))
                : [];

            if (
                $gaps === []
                || !$context->report(
                    $node,
                    $gaps[0]['blankLines'] === 1
                        ? 'Expected one blank line between the description and the annotations of the doc comment.'
                        : 'Expected no blank line between two annotations of the doc comment.',
                    trivia: $trivia
                )
            ) {
                continue;
            }

            $eol = str_contains($trivia->text, "\r\n") ? "\r\n" : "\n";
            $lines = explode("\n", str_replace("\r\n", "\n", $trivia->text));

            foreach (array_reverse($gaps) as ['end' => $end, 'start' => $start, 'blankLines' => $blankLines]) {
                $blankLine = self::createBlankLine($lines[$start - 1]);
                array_splice($lines, $end, $start - $end - 1, array_fill(0, $blankLines, $blankLine));
            }

            $node->replaceTrivia($trivia, $trivia->withText(implode($eol, $lines)));
        }
    }
}
