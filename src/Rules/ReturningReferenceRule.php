<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Node;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class ReturningReferenceRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.returningReference',
                Domain::state('forbidden'),
                'A function returning a reference, `function &find()`, which is only reported'
            ),
        ];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [FunctionLikeNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof FunctionLikeNode || $node->ampersand === null) {
            return;
        }

        $context->report($node->ampersand, 'Returning a reference is forbidden.', fixable: false);
    }
}
