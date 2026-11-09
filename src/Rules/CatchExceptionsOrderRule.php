<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domains\Words;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use DressCode\Violation;
use PhpSyntax\Node;
use PhpSyntax\Nodes\CatchNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class CatchExceptionsOrderRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.catchExceptionsOrder',
                new Words([
                    'alphabetical' => 'the types in the natural order of their written names, regardless of letter '
                        . 'case and a leading backslash',
                ]),
                'The order of the types of a multi-catch `catch (A | B $e)`, in natural order regardless of letter case'
            ),
        ];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [CatchNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof CatchNode || count($node->types) < 2) {
            return;
        }

        $types = $node->types->getItems();
        $names = array_map(static fn (NameNode $type) => $type->text, $types);
        $sorted = $names;
        usort($sorted, static fn (string $a, string $b) => strnatcasecmp(ltrim($a, '\\'), ltrim($b, '\\')));

        if ($sorted === $names) {
            return;
        }

        if (!$context->report(
            $node,
            'The caught types must be in alphabetical order: ' . Violation::formatCode(implode(' | ', $sorted)) . '.',
            // a comment stays with its token while only the names change places
            fixable: !$node->openParen->hasCommentUpTo($node->closeParen)
        )) {
            return;
        }

        foreach ($types as $i => $type) {
            $type->text = $sorted[$i];
        }
    }
}
