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
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\PlainNodeList;
use PhpSyntax\Nodes\Statement\ExpressionStatementNode;
use PhpSyntax\Nodes\Statement\ReturnNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure, analyses: [NameResolver::class])]
final class UselessVariableRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.uselessVariable',
                Domain::state('forbidden'),
                'A variable assigned only to be returned by the next statement, `$a = f(); return $a;`, which is'
                . ' written `return f();`'
            ),
        ];
    }

    private static function findAssignment(ReturnNode $return, string $name): ?ExpressionStatementNode
    {
        $statement = $return->parent instanceof PlainNodeList ? $return->getPreviousSibling() : null;
        $assignment = $statement instanceof ExpressionStatementNode ? $statement->expression : null;

        return $assignment instanceof AssignmentNode
            && $assignment->target instanceof VariableNode
            && $assignment->target->plainName === $name
            ? $statement
            : null;
    }

    /** Whether a `@var` of the doc comment of the statement names the variable, which keeps it for its type. */
    private static function isAnnotated(ExpressionStatementNode $statement, string $name): bool
    {
        $docComment = $statement->getDocComment();

        return $docComment !== null
            && preg_match('~@(?:phpstan-|psalm-)?var\s.*\$' . preg_quote($name, '~') . '\b~', $docComment->text) === 1;
    }

    private static function isOnlyReturned(
        Node $target,
        VariableNode $returned,
        string $name,
        RuleContext $context,
    ): bool
    {
        $owner = VariableScope::findScope($returned, $name);

        if ($owner === null || $owner->ampersand !== null) {
            return false;
        }

        $scope = VariableScope::of($owner, $context);

        return !$scope->isDynamic()
            && !$scope->isBound($name)
            && $scope->findOccurrences($name) === [$target, $returned];
    }

    /**
     * The `return` keeps the blank lines before it, or takes those before the assignment where the assignment opened
     * its list.
     */
    private static function returnDirectly(
        ReturnNode $return,
        ExpressionStatementNode $statement,
        AssignmentNode $assignment,
        string $lineEnding,
    ): void
    {
        $keyword = $return->returnKeyword;
        $opensList = $statement->getPreviousSibling() === null;
        $blankLines = $keyword->countBlankLinesBefore();
        $value = $assignment->expression;

        if (!$statement->getLastToken()->isFollowedByLineEnding()) {
            $statement->getLastToken()->setTrailingTrivia([]);
        }

        $statement->remove(mergeBlankLines: true);
        $return->expression?->replaceWithExpression($value->setEdgeTrivia([], []));

        if (!$opensList && $keyword->startsLine()) {
            $keyword->setBlankLinesBefore($blankLines, $lineEnding);
        }
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [ReturnNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (
            !$node instanceof ReturnNode
            || !($returned = $node->expression) instanceof VariableNode
            || ($name = $returned->plainName) === null
            || ($statement = self::findAssignment($node, $name)) === null
            || !($assignment = $statement->expression) instanceof AssignmentNode
        ) {
            return;
        }

        $target = $assignment->target;

        if (
            self::isAnnotated($statement, $name)
            || !self::isOnlyReturned($target, $returned, $name, $context)
            || !$context->report(
                $target,
                "The variable `\$$name` is assigned only to be returned.",
                fixable: !$statement->getFirstToken()->hasCommentUpTo($node->semicolon)
            )
        ) {
            return;
        }

        self::returnDirectly($node, $statement, $assignment, $context->style->lineEnding);
    }
}
