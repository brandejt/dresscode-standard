<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\StaticPropertyFetchNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class VariableVariablesRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.variableVariables',
                Domain::state('forbidden'),
                'A variable named by an expression, `$$a`, `${expr}` or `A::$$b`, which is only reported'
            ),
        ];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [VariableNode::class, StaticPropertyFetchNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (
            (!$node instanceof VariableNode && !$node instanceof StaticPropertyFetchNode)
            || !$node->name instanceof ExpressionNode
        ) {
            return;
        }

        $what = $node instanceof VariableNode ? 'variable' : 'static property';
        $context->report($node, "The $what must be named by an identifier, not by an expression.", fixable: false);
    }
}
