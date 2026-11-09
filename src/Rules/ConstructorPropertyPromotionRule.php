<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class ConstructorPropertyPromotionRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.constructorPropertyPromotion',
                Domain::state('forbidden'),
                'A constructor parameter promoted to a property, which is only reported'
            ),
        ];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [MethodNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof MethodNode || !$node->isConstructor()) {
            return;
        }

        foreach ($node->parameters->getItems() as $parameter) {
            if (!$parameter->promoted) {
                continue;
            }

            $name = '$' . $parameter->variable->plainName;
            $context->report(
                $parameter->variable,
                "The property `$name` must be declared in the class, not promoted in the constructor.",
                fixable: false
            );
        }
    }
}
