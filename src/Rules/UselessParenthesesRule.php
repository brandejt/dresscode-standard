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
use PhpSyntax\Nodes\CaseNode;
use PhpSyntax\Nodes\Expression\ArrayAccessNode;
use PhpSyntax\Nodes\Expression\AssignmentByReferenceNode;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\BinaryOpNode;
use PhpSyntax\Nodes\Expression\ClassConstantFetchNode;
use PhpSyntax\Nodes\Expression\CloneNode;
use PhpSyntax\Nodes\Expression\ConstantFetchNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\IncludeNode;
use PhpSyntax\Nodes\Expression\MethodCallNode;
use PhpSyntax\Nodes\Expression\NewNode;
use PhpSyntax\Nodes\Expression\ParenthesizedNode;
use PhpSyntax\Nodes\Expression\PrintNode;
use PhpSyntax\Nodes\Expression\PropertyFetchNode;
use PhpSyntax\Nodes\Expression\StaticMethodCallNode;
use PhpSyntax\Nodes\Expression\StaticPropertyFetchNode;
use PhpSyntax\Nodes\Expression\TernaryNode;
use PhpSyntax\Nodes\Expression\UnaryOpNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Expression\YieldNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\SeparatedNodeList;
use PhpSyntax\Nodes\Statement\BreakNode;
use PhpSyntax\Nodes\Statement\ContinueNode;
use PhpSyntax\Nodes\Statement\EchoNode;
use PhpSyntax\Nodes\Statement\ReturnNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class UselessParenthesesRule extends NodeRule
{
    private const array LOGICAL = [
        Token::BooleanAnd,
        Token::BooleanOr,
        Token::LogicalAnd,
        Token::LogicalOr,
        Token::LogicalXor,
    ];

    /** Slevomat's groups of the arithmetic and concatenation operators; it removes parentheses only within one group */
    private const array OPERATOR_GROUPS = ['**' => 1, '*' => 2, '/' => 2, '%' => 3, '+' => 4, '-' => 4, '.' => 5];

    private const array CASTS = [
        Token::IntCast,
        Token::FloatCast,
        Token::StringCast,
        Token::ArrayCast,
        Token::ObjectCast,
        Token::BoolCast,
        Token::UnsetCast,
    ];

    private const array INCLUDES = [Token::Include, Token::IncludeOnce, Token::Require, Token::RequireOnce];

    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.uselessParentheses',
                Domain::state('forbidden'),
                'Parentheses that change nothing, `foo(($a))`, `$x = ($a + $b);` or `($a) ? $b : $c`, '
                . 'which are removed',
                notes: [
                    'Parentheses stay around an assignment, around a ternary condition holding a logical operation, '
                    . 'next to a logical operator, and between operators of different precedence groups even where PHP '
                    . 'does not need them, `$a . ($b + $c)`.',
                ]
            ),
        ];
    }

    /** Parentheses Slevomat leaves alone wherever they stand. */
    private static function isExempt(ParenthesizedNode $node): bool
    {
        $first = $node->expression->getFirstToken();

        return self::isPrecededBy($node, [...self::CASTS, '~', Token::New, ...self::INCLUDES])
            || self::isFollowedBy($node, ['('])
            || $first->is([Token::Clone, Token::Yield, Token::YieldFrom, Token::ArrayCast, ...self::INCLUDES])
            || self::holds($node->expression, self::isAssignment(...));
    }

    /** An operand of `return`, `echo` and their kin, left to `expressions.parenthesesAfterConstruct`. */
    private static function isConstructOperand(ParenthesizedNode $node): bool
    {
        $parent = $node->parent;

        return match (true) {
            $parent instanceof ReturnNode,
            $parent instanceof PrintNode,
            $parent instanceof CloneNode,
            $parent instanceof BreakNode,
            $parent instanceof ContinueNode,
            $parent instanceof IncludeNode,
            $parent instanceof CaseNode => true,
            $parent instanceof YieldNode => $parent->value === $node,
            $parent instanceof SeparatedNodeList => $parent->parent instanceof EchoNode,
            default => false,
        };
    }

    private static function isUseless(ParenthesizedNode $node): bool
    {
        return self::isAroundTernaryCondition($node)
            || self::isAroundChain($node)
            || $node->expression instanceof StringNode
            || self::isAroundArithmetic($node)
            || self::isAroundNew($node);
    }

    private static function isAroundTernaryCondition(ParenthesizedNode $node): bool
    {
        return $node->parent instanceof TernaryNode
            && $node->parent->condition === $node
            && !self::isPrecededBy($node, self::LOGICAL)
            && !self::holds($node->expression, self::isLogicalOrTernary(...));
    }

    private static function isAroundChain(ParenthesizedNode $node): bool
    {
        $expression = $node->expression;
        $negated = $expression instanceof UnaryOpNode && $expression->operator->is('!');

        return self::isChain($negated ? $expression->expression : $expression)
            && !self::isPrecededBy($node, [...self::LOGICAL, Token::Case])
            && !self::isFollowedBy($node, ['?', Token::ShiftRight])
            && !self::holds($expression, self::isArithmetic(...));
    }

    private static function isChain(ExpressionNode|NameNode $expression): bool
    {
        return match (true) {
            $expression instanceof NameNode,
            $expression instanceof VariableNode,
            $expression instanceof ConstantFetchNode => true,
            $expression instanceof PropertyFetchNode,
            $expression instanceof MethodCallNode => self::isChain($expression->object),
            $expression instanceof ArrayAccessNode => self::isChain($expression->expression),
            $expression instanceof FunctionCallNode => self::isChain($expression->name),
            $expression instanceof StaticMethodCallNode,
            $expression instanceof StaticPropertyFetchNode,
            $expression instanceof ClassConstantFetchNode => self::isChain($expression->class),
            default => false,
        };
    }

    private static function isAroundArithmetic(ParenthesizedNode $node): bool
    {
        $operators = self::collectOperators($node->expression);

        if (
            $operators === null
            || $operators === []
            || (count($operators) > 1 && !self::isAssignedValue($node))
            || self::isPrecededBy($node, [...self::LOGICAL, '!'])
            || self::isFollowedBy($node, self::LOGICAL)
        ) {
            return false;
        }

        $before = $node->openParen->getPrevious()->text ?? '';
        $after = $node->closeParen->getNext()->text ?? '';
        $last = $operators[count($operators) - 1];

        return (!isset(self::OPERATOR_GROUPS[$before]) || self::canFollow($before, $operators[0]))
            && (!isset(self::OPERATOR_GROUPS[$after])
                || self::OPERATOR_GROUPS[$after] === self::OPERATOR_GROUPS[$last]);
    }

    /**
     * The arithmetic and concatenation operators the expression is written with, down through its operands, in the
     * order they stand; null where another operator stands among them.
     *
     * @return list<string>|null
     */
    private static function collectOperators(ExpressionNode $expression): ?array
    {
        if ($expression instanceof UnaryOpNode && $expression->operator->is(['+', '-'])) {
            $operand = self::collectOperators($expression->expression);

            return $operand === null ? null : [$expression->operator->text, ...$operand];
        }

        if (!$expression instanceof BinaryOpNode) {
            return [];
        }

        if (!isset(self::OPERATOR_GROUPS[$expression->operator->text])) {
            return null;
        }

        $left = self::collectOperators($expression->left);
        $right = self::collectOperators($expression->right);

        return $left === null || $right === null ? null : [...$left, $expression->operator->text, ...$right];
    }

    private static function isAssignedValue(ParenthesizedNode $node): bool
    {
        return self::isPrecededBy($node, ['=']) && self::isFollowedBy($node, [';']);
    }

    /** Whether the operator before the parentheses may take the first one inside along, as Slevomat's table says. */
    private static function canFollow(string $before, string $first): bool
    {
        return self::OPERATOR_GROUPS[$before] === self::OPERATOR_GROUPS[$first]
            && $before !== '-'
            && $before !== '/'
            && $before !== '%';
    }

    private static function isAroundNew(ParenthesizedNode $node): bool
    {
        return $node->expression instanceof NewNode && self::isFollowedBy($node, [',', ';', ']']);
    }

    private static function isAssignment(ExpressionNode $expression): bool
    {
        return $expression instanceof AssignmentNode || $expression instanceof AssignmentByReferenceNode;
    }

    private static function isLogicalOrTernary(ExpressionNode $expression): bool
    {
        return $expression instanceof TernaryNode || ($expression instanceof BinaryOpNode && $expression->isLogical());
    }

    private static function isArithmetic(ExpressionNode $expression): bool
    {
        return ($expression instanceof BinaryOpNode && isset(self::OPERATOR_GROUPS[$expression->operator->text]))
            || ($expression instanceof UnaryOpNode && $expression->operator->is(['+', '-']));
    }

    /** @param callable(ExpressionNode): bool $predicate */
    private static function holds(ExpressionNode $expression, callable $predicate): bool
    {
        return array_any([$expression, ...$expression->find(ExpressionNode::class)], $predicate);
    }

    /** @param list<int|string> $kinds */
    private static function isPrecededBy(ParenthesizedNode $node, array $kinds): bool
    {
        return $node->openParen->getPrevious()?->is($kinds) === true;
    }

    /** @param list<int|string> $kinds */
    private static function isFollowedBy(ParenthesizedNode $node, array $kinds): bool
    {
        return $node->closeParen->getNext()?->is($kinds) === true;
    }

    /**
     * Whether a comment stands between the parentheses and what they hold, which dropping them would lose; those
     * before and after the parentheses stay around the expression.
     */
    private static function holdsComment(ParenthesizedNode $node): bool
    {
        return $node->openParen->hasTrailingComment()
            || $node->closeParen->hasLeadingComment()
            || $node->expression->hasLeadingComment()
            || $node->expression->hasTrailingComment();
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [ParenthesizedNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (
            !$node instanceof ParenthesizedNode
            || self::isExempt($node)
            || self::isConstructOperand($node)
            || !self::isUseless($node)
            || self::holdsComment($node)
            || !$node->isRedundant()
            || !$context->report(
                $node,
                'The parentheses around '
                . Violation::formatCode((string) preg_replace('~\s+~', ' ', $node->expression->text))
                . ' are useless and must be removed.'
            )
        ) {
            return;
        }

        $inner = $node->expression->withoutEdgeTrivia();
        $node->replaceWith($inner);
        $previous = $inner->getFirstToken()->getPrevious();

        if ($previous?->getTrailingSpace() === '' && preg_match('~\w$~', $previous->text) === 1) {
            $previous->setTrailingSpace(' ');
        }
    }
}
