<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\Risk;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Node;
use PhpSyntax\Nodes\ClosureUseListNode;
use PhpSyntax\Nodes\Expression\ClosureNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure, analyses: [NameResolver::class])]
final class UnusedClosureUseRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.unusedClosureUse',
                Domain::state('forbidden'),
                'A variable captured by the `use` of a closure whose body never uses it, '
                . 'which is removed from the `use`'
            ),
        ];
    }

    /** Whether a comment stands in the `use` clause or on its edges, which removing the whole clause would drop. */
    private static function hasComment(ClosureUseListNode $uses): bool
    {
        return $uses->hasInnerComment() || $uses->hasLeadingComment() || $uses->hasTrailingComment();
    }

    /**
     * The whitespace after the clause replaces the whitespace before it, so that the brace or the colon of the return
     * type keeps its place, unless a comment stands before the clause.
     */
    private static function removeClause(ClosureNode $closure, ClosureUseListNode $uses): void
    {
        if (!$closure->closeParen->hasTrailingComment()) {
            $closure->closeParen->setTrailingTrivia($uses->getLastToken()->trailingTrivia);
        }

        $closure->uses = null;
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [ClosureNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof ClosureNode || $node->uses === null) {
            return;
        }

        $scope = VariableScope::of($node, $context);

        if ($scope->isDynamic()) {
            return;
        }

        foreach ($node->uses->items->getItems() as $use) {
            $name = $use->variable->plainName;

            if ($name === null || $scope->findOccurrences($name) !== []) {
                continue;
            }

            $uses = $node->uses;
            $last = count($uses->items) === 1;
            $because = $use->ampersand === null
                ? null
                : "the reference creates `\$$name` in the scope around the closure";

            if (!$context->report(
                $use,
                "The closure captures `\$$name` but never uses it.",
                risk: $because === null ? null : Risk::BehaviorChanges,
                fixable: !$last || !self::hasComment($uses),
                because: $because
            )) {
                continue;
            }

            if ($last) {
                self::removeClause($node, $uses);
            } else {
                $use->remove();
            }
        }
    }
}
