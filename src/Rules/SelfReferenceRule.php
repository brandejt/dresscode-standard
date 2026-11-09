<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Decision;
use DressCode\Domains\Words;
use DressCode\NodeRule;
use DressCode\RuleContext;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Analyses\NameResolver;
use PhpSyntax\Node;
use PhpSyntax\Nodes\Expression\ClassConstantFetchNode;
use PhpSyntax\Nodes\Expression\InstanceofNode;
use PhpSyntax\Nodes\Expression\StaticMethodCallNode;
use PhpSyntax\Nodes\Expression\StaticPropertyFetchNode;
use PhpSyntax\Nodes\NameNode;
use PhpSyntax\Nodes\Statement\ClassNode;
use PhpSyntax\Nodes\Statement\EnumNode;
use PhpSyntax\Nodes\Statement\InterfaceNode;
use PhpSyntax\Nodes\Type\NamedTypeNode;
use PhpSyntax\SymbolKind;
use PhpSyntax\Token;

#[RuleInfo(Stage::Structure, analyses: [NameResolver::class])]
final class SelfReferenceRule extends NodeRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.selfReference',
                new Words(['self' => 'the class named `self` inside itself']),
                'How a class refers to itself where `qualification.currentClass` does not look: in a type declaration, '
                . 'and in an interface or an enum also in an expression'
            ),
        ];
    }

    private static function isInScope(NameNode $name, ClassNode|EnumNode|InterfaceNode $class): bool
    {
        $parent = $name->parent;

        return $parent instanceof NamedTypeNode
            || (!$class instanceof ClassNode
                && ($parent instanceof StaticMethodCallNode
                    || $parent instanceof ClassConstantFetchNode
                    || $parent instanceof StaticPropertyFetchNode
                    || $parent instanceof InstanceofNode));
    }

    /** @return list<class-string> */
    public function getVisitedNodes(): array
    {
        return [NameNode::class];
    }

    public function enter(Node|Token $node, RuleContext $context): void
    {
        if (!$node instanceof NameNode || !$node->isReference() || $node->symbolKind !== SymbolKind::ClassLike) {
            return;
        }

        $class = $node->findClassScope();

        if (
            !($class instanceof ClassNode || $class instanceof InterfaceNode || $class instanceof EnumNode)
            || !self::isInScope($node, $class)
        ) {
            return;
        }

        $resolver = $context->getAnalysis(NameResolver::class);

        if (
            strcasecmp($resolver->resolveClass($node), (string) $resolver->getDeclaredName($class)) !== 0
            || !$context->report($node, "The current class `{$class->name->text}` must be referenced as `self`.")
        ) {
            return;
        }

        $node->text = 'self';
    }
}
