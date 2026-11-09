<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use DressCode\Violation;
use PhpSyntax\Node;
use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Nodes\AttributeNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class AttributesJoiningRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.attributesJoining',
                Domain::state('forbidden'),
                'Several attributes in one group `#[A, B]`, which is only reported'
            ),
        ];
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [AttributeGroupNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof AttributeGroupNode || count($node->items) < 2) {
            return;
        }

        $names = array_map(
            static fn (AttributeNode $attribute) => Violation::formatCode($attribute->name->text),
            $node->items->getItems()
        );
        $last = array_pop($names);
        $context->report(
            $node,
            'The attributes ' . implode(', ', $names) . " and $last must each have a group of their own.",
            fixable: false
        );
    }
}
