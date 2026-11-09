<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class SuperGlobalsRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.superGlobals',
                Domain::state('forbidden'),
                'A superglobal variable such as `$_GET` or `$_SERVER`, which is only reported'
            ),
        ];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [VariableNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof VariableNode || !in_array($node->plainName, VariableScope::SUPER_GLOBALS, true)) {
            return;
        }

        $context->report($node, "The superglobal `\$$node->plainName` is forbidden.", fixable: false);
    }
}
