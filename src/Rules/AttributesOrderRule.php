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
use PhpSyntax\Nodes\AttributeAwareNode;
use PhpSyntax\Nodes\AttributeGroupNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class AttributesOrderRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.attributesOrder',
                new Words([
                    'alphabetical' => 'the groups in the natural order of the name of the first attribute of each, '
                        . 'regardless of letter case and a leading backslash',
                ]),
                'The order of the attribute groups of a declaration, by the name of their first attributes in natural '
                . 'order regardless of letter case'
            ),
        ];
    }

    private static function getName(AttributeGroupNode $group): string
    {
        return $group->items[0]->name->text;
    }

    /**
     * Whether a comment sits after a group or before one but the first, where it would stay with its line while the
     * attributes move; what stands before the first group belongs to the declaration.
     *
     * @param list<AttributeGroupNode> $groups
     */
    private static function hasCommentBetween(array $groups): bool
    {
        return array_any(
            $groups,
            static fn ($group, $i) => $group->hasTrailingComment() || ($i > 0 && $group->hasLeadingComment())
        );
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [AttributeAwareNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof AttributeAwareNode || count($node->attributes) < 2) {
            return;
        }

        $groups = $node->attributes->getItems();
        $sorted = $groups;
        usort(
            $sorted,
            static fn (AttributeGroupNode $a, AttributeGroupNode $b) => strnatcasecmp(
                ltrim(self::getName($a), '\\'),
                ltrim(self::getName($b), '\\')
            )
        );

        if ($sorted === $groups) {
            return;
        }

        $names = array_map(
            static fn (AttributeGroupNode $group) => Violation::formatCode(self::getName($group)),
            $sorted
        );

        if (!$context->report(
            $groups[0],
            'The attribute groups must be in alphabetical order: ' . implode(', ', $names) . '.',
            fixable: !self::hasCommentBetween($groups)
        )) {
            return;
        }

        $lists = array_map(static fn (AttributeGroupNode $group) => clone $group->items, $sorted);

        foreach ($groups as $i => $group) {
            $group->items = $lists[$i];
        }
    }
}
