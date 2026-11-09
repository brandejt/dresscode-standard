<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Node;
use PhpSyntax\Nodes\ArgumentListNode;
use PhpSyntax\Nodes\Expression\IssetNode;
use PhpSyntax\Nodes\Statement\UnsetNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class TrailingCommaInCallRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.trailingCommaInCall',
                Domain::state('forbidden'),
                'A comma after the last argument of a call, an instantiation, an attribute, `isset()` or `unset()`, '
                . 'which is removed'
            ),
        ];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [ArgumentListNode::class, IssetNode::class, UnsetNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        $arguments = match (true) {
            $node instanceof ArgumentListNode => $node->items,
            $node instanceof IssetNode, $node instanceof UnsetNode => $node->variables,
            default => null,
        };

        if (
            $arguments === null
            || !$arguments->hasTrailingSeparator()
            || !$context->report(
                $arguments->getTrailingSeparator(),
                'The last argument must not be followed by a comma.'
            )
        ) {
            return;
        }

        $arguments->setTrailingSeparator(null);
    }
}
