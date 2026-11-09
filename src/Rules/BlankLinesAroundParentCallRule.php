<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Claim;
use DressCode\Decision;
use DressCode\Domain;
use DressCode\Gap;
use DressCode\GapRule;
use DressCode\RuleInfo;
use DressCode\Stage;
use DressCode\Values;
use PhpSyntax\Node;
use PhpSyntax\Nodes\CaseNode;
use PhpSyntax\Nodes\Expression\CastNode;
use PhpSyntax\Nodes\Expression\StaticMethodCallNode;
use PhpSyntax\Nodes\Expression\UnaryOpNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\BlockNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Formatting)]
final class BlankLinesAroundParentCallRule extends GapRule
{
    private Claim $claim;

    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.blankLinesAroundParentCall',
                Domain::blankLines(),
                'Blank lines before a statement calling a parent method or constructor that is not the first of its '
                . 'block, and after one that is not the last'
            ),
        ];
    }

    /** @phpstan-assert-if-true ExpressionStatementNode $statement */
    private static function isParentCall(Node|Token $statement): bool
    {
        if (!$statement instanceof ExpressionStatementNode) {
            return false;
        }

        $expression = $statement->expression;

        while (
            $expression instanceof CastNode
            || ($expression instanceof UnaryOpNode && $expression->operator->is('@'))
        ) {
            $expression = $expression->expression;
        }

        return $expression instanceof StaticMethodCallNode
            && $expression->class instanceof NameNode
            && $expression->class->equals('parent');
    }

    public function configure(Values $values): void
    {
        $this->claim = Claim::blankLines($values->get('brandejt.blankLinesAroundParentCall')->getCountRange());
    }

    /**
     * @return array<string, array<string, array{
     *     \Closure(Gap): (Claim|null)|Claim|null,
     *     \Closure(Gap): (Claim|null)|Claim|null
     * }>>
     */
    public function getClaims(): array
    {
        $claims = [
            'statements:item' => [
                fn (Gap $gap) => $gap->index > 0 && self::isParentCall($gap->value) ? $this->claim : null,
                fn (Gap $gap) => self::isParentCall($gap->value) && $gap->value->getNextSibling() !== null
                    ? $this->claim
                    : null,
            ],
        ];

        // a class of its own rather than `*`, which the core claims for `blankLines.betweenStatementsInFunction`
        return [BlockNode::class => $claims, CaseNode::class => $claims];
    }
}
