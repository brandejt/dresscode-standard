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
                new Words(['alphabetical' => 'the types sorted by their written names regardless of letter case']),
                'The order of the types of a multi-catch `catch (A | B $e)`, alphabetical regardless of letter case'
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
        usort($sorted, static fn (string $a, string $b) => strcasecmp(ltrim($a, '\\'), ltrim($b, '\\')));

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
