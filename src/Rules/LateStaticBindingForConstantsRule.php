<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domain;
use DressCode\NodeRule;
use DressCode\Risk;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Node;
use PhpSyntax\Nodes\AnonymousClassNode;
use PhpSyntax\Nodes\Expression\ClassConstantFetchNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\EnumNode;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure)]
final class LateStaticBindingForConstantsRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.lateStaticBindingForConstants',
                Domain::state('forbidden'),
                'A class constant fetched with `static::`, which is written `self::`'
            ),
        ];
    }

    private static function mayHaveSubclass(Node $node): bool
    {
        $scope = $node->findClassScope();

        return !$scope instanceof AnonymousClassNode
            && !$scope instanceof EnumNode
            && !($scope instanceof ClassNode && $scope->modifiers->final);
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [ClassConstantFetchNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (
            !$node instanceof ClassConstantFetchNode
            || !$node->class instanceof NameNode
            || !$node->class->equals('static')
            || $node->plainName === null
            || strcasecmp($node->plainName, 'class') === 0
            // where no subclass can exist, `qualification.staticInFinalClass` of the core decides
            || !self::mayHaveSubclass($node)
            || !$context->report(
                $node,
                "`static::$node->plainName` must be written `self::$node->plainName`.",
                risk: Risk::BehaviorChanges,
                because: "a subclass may redeclare `$node->plainName`, which `static::` reads and `self::` does not"
            )
        ) {
            return;
        }

        $node->class->text = 'self';
    }
}
