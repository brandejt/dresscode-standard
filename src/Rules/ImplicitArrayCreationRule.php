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
use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\CatchNode;
use PhpSyntax\Nodes\ClosureUseNode;
use PhpSyntax\Nodes\Expression\ArrayAccessNode;
use PhpSyntax\Nodes\Expression\ArrowFunctionNode;
use PhpSyntax\Nodes\Expression\AssignmentByReferenceNode;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\CombinedAssignmentNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure, analyses: [NameResolver::class])]
final class ImplicitArrayCreationRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.implicitArrayCreation',
                Domain::state('forbidden'),
                'An element assigned to a variable nothing created before, `$a[] = 1` without `$a = []`, which is only'
                . ' reported'
            ),
        ];
    }

    /** @param list<FunctionCallNode|VariableNode> $occurrences */
    private static function findImplicitCreation(array $occurrences): ?VariableNode
    {
        foreach ($occurrences as $occurrence) {
            if ($occurrence instanceof FunctionCallNode) {
                continue;
            }

            if (self::isElementAssignment($occurrence)) {
                return $occurrence;
            }

            if (self::creates($occurrence)) {
                return null;
            }
        }

        return null;
    }

    private static function isElementAssignment(VariableNode $variable): bool
    {
        $element = self::findOutermostElement($variable);

        $assignment = $element->parent;

        return $element !== $variable
            && (
                $assignment instanceof AssignmentNode
                || $assignment instanceof CombinedAssignmentNode
                || $assignment instanceof AssignmentByReferenceNode
            )
            && $assignment->target === $element;
    }

    /**
     * Whether the occurrence makes the variable exist: a value is assigned to it, it is bound by reference or caught,
     * or it is passed to a call, whose parameter may take it by reference, an element of it included.
     */
    private static function creates(VariableNode $variable): bool
    {
        // an arrow function captures by value, so what it assigns does not reach the scope around it
        if ($variable->findAncestor(FunctionLikeNode::class) instanceof ArrowFunctionNode) {
            return false;
        }

        $parent = $variable->parent;

        return VariableScope::isAssignmentTarget($variable, byReference: true)
            || ($parent instanceof CombinedAssignmentNode && $parent->target === $variable)
            || $parent instanceof AssignmentByReferenceNode
            || ($parent instanceof ClosureUseNode && $parent->ampersand !== null)
            || $parent instanceof CatchNode
            || self::findOutermostElement($variable)->parent instanceof ArgumentNode;
    }

    private static function findOutermostElement(VariableNode $variable): ArrayAccessNode|VariableNode
    {
        $node = $variable;

        while ($node->parent instanceof ArrayAccessNode && $node->parent->expression === $node) {
            $node = $node->parent;
        }

        return $node;
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
            $element = $scope->isBound($name) ? null : self::findImplicitCreation($occurrences);

            if ($element === null) {
                continue;
            }

            $context->report(
                $element,
                "The array `\$$name` must be created explicitly before an element is assigned to it.",
                fixable: false
            );
        }
    }
}
