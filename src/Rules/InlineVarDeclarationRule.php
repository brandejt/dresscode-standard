<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Analyses\PhpDoc;
use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use DressCode\Violation;
use PHPStan\PhpDocParser\Ast\Attribute;
use PHPStan\PhpDocParser\Ast\PhpDoc\InvalidTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\VarTagValueNode;
use PhpSyntax\Node;
use PhpSyntax\Nodes\ClassLikeNode;
use PhpSyntax\Nodes\Expression\AssignmentByReferenceNode;
use PhpSyntax\Nodes\Expression\CombinedAssignmentNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\PlainNodeList;
use PhpSyntax\Nodes\Statement\ConstNode;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Nodes\Statement\ForeachNode;
use PhpSyntax\Nodes\Statement\FunctionNode;
use PhpSyntax\Nodes\Statement\ReturnNode;
use PhpSyntax\Nodes\Statement\StaticNode;
use PhpSyntax\Nodes\Statement\WhileNode;
use PhpSyntax\Nodes\StatementNode;
use PhpSyntax\Nodes\StaticVariableNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure, modifiesComments: true, analyses: [PhpDoc::class])]
final class InlineVarDeclarationRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.inlineVarDeclaration',
                Domain::state('required'),
                'An inline `@var` written `@var Type $x` above the statement that assigns `$x`, '
                . 'or first in the body of the loop that does'
            ),
        ];
    }

    /**
     * The statement and, for the first statement of a block, the statement owning the block: the loop whose value
     * the annotation documents from inside.
     *
     * @return list<Node|null>
     */
    private static function findDocumented(StatementNode $statement): array
    {
        $list = $statement->parent;

        return $list instanceof PlainNodeList && $list->indexOf($statement) === 0
            ? [$statement, $list->parent?->parent]
            : [$statement];
    }

    private static function assigns(?Node $statement, string $name): bool
    {
        $places = match (true) {
            $statement instanceof ExpressionStatementNode, $statement instanceof ReturnNode => [$statement->expression],
            $statement instanceof ForeachNode => [$statement->key, $statement->value],
            $statement instanceof WhileNode => [$statement->condition],
            $statement instanceof StaticNode => [$statement->variables],
            default => [],
        };

        foreach (array_filter($places) as $place) {
            $variables = $place instanceof VariableNode ? [$place] : $place->find(VariableNode::class);

            if (array_any(
                $variables,
                static fn (VariableNode $variable) => '$' . $variable->plainName === $name && self::isWritten($variable)
            )) {
                return true;
            }
        }

        return false;
    }

    private static function isWritten(VariableNode $variable): bool
    {
        $parent = $variable->parent;

        return VariableScope::isAssignmentTarget($variable, byReference: true)
            || ($parent instanceof AssignmentByReferenceNode && $parent->target === $variable)
            || ($parent instanceof CombinedAssignmentNode
                && $parent->target === $variable
                && $parent->operator->text === '??=')
            || ($parent instanceof StaticVariableNode && $parent->variable === $variable)
            || ($parent instanceof ParameterNode && $parent->variable === $variable);
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [StatementNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (
            !$node instanceof StatementNode
            || $node instanceof ClassLikeNode
            || $node instanceof FunctionNode
            || $node instanceof ConstNode
            || ($docComment = $node->getDocComment()) === null
        ) {
            return;
        }

        $text = $docComment->text;

        foreach ($context->getAnalysis(PhpDoc::class)->parse($docComment)->children as $tag) {
            if (!$tag instanceof PhpDocTagNode || $tag->name !== '@var') {
                continue;
            }

            $lineInTrivia = $tag->getAttribute(Attribute::START_LINE) - 1;
            $value = $tag->value;

            if ($value instanceof VarTagValueNode && $value->variableName !== '') {
                if (!array_any(
                    self::findDocumented($node),
                    static fn (?Node $statement) => self::assigns($statement, $value->variableName)
                )) {
                    $context->report(
                        $node,
                        "The inline `@var` of `$value->variableName` must stand above the statement assigning it.",
                        trivia: $docComment,
                        lineInTrivia: $lineInTrivia,
                        fixable: false
                    );
                }

                continue;
            }

            $written = trim("@var $value");
            $swapped = $value instanceof InvalidTagValueNode
                && preg_match('~^(\$\w+)\s+(\S+)$~', $value->value, $match) === 1;

            if (!$swapped) {
                $context->report(
                    $node,
                    'The inline annotation ' . Violation::formatCode($written)
                    . ' must be written `@var Type $variable`.',
                    trivia: $docComment,
                    lineInTrivia: $lineInTrivia,
                    fixable: false
                );

                continue;
            }

            [, $variable, $type] = $match;

            if ($context->report(
                $node,
                'The inline annotation ' . Violation::formatCode($written)
                . ' must be written ' . Violation::formatCode("@var $type $variable") . '.',
                trivia: $docComment,
                lineInTrivia: $lineInTrivia
            )) {
                $text = preg_replace(
                    '~(@var\s+)' . preg_quote($variable, '~') . '(\s+)' . preg_quote($type, '~') . '~',
                    '${1}' . $type . '${2}' . $variable,
                    $text,
                    1
                );
            }
        }

        if ($text !== $docComment->text) {
            $node->replaceDocComment($docComment->withText($text));
        }
    }
}
