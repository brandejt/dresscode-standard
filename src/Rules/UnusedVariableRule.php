<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\CombinedAssignmentNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\PostfixOpNode;
use PhpSyntax\Nodes\Expression\PrefixOpNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure, analyses: [NameResolver::class])]
final class UnusedVariableRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.unusedVariable',
                Domain::state('forbidden'),
                'A variable that its function only assigns and never reads, which is only reported; '
                . '`$count++;` and `$sum += 1;` as statements of their own only assign it'
            ),
        ];
    }

    /** @param list<FunctionCallNode|VariableNode> $occurrences */
    private static function isOnlyAssigned(array $occurrences): bool
    {
        return array_all(
            $occurrences,
            static fn (Node $occurrence) => $occurrence instanceof VariableNode
                && (VariableScope::isAssignmentTarget($occurrence) || self::isStandaloneChange($occurrence))
        );
    }

    private static function isStandaloneChange(VariableNode $variable): bool
    {
        $parent = $variable->parent;

        return ($parent instanceof PrefixOpNode
            || $parent instanceof PostfixOpNode
            || $parent instanceof CombinedAssignmentNode)
            && $parent->target === $variable
            && $parent->parent instanceof ExpressionStatementNode;
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return VariableScope::SCOPES;
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!VariableScope::isScope($node)) {
            return;
        }

        $scope = VariableScope::of($node, $context);

        if ($scope->isDynamic()) {
            return;
        }

        foreach ($scope->getOccurrences() as $name => $occurrences) {
            if ($scope->isBound($name) || !self::isOnlyAssigned($occurrences)) {
                continue;
            }

            $context->report($occurrences[0], "The variable `\$$name` is assigned but never read.", fixable: false);
        }
    }
}
