<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\GlobalCalls;
use DressCode\RuleContext;
use PhpSyntax\Node;
use PhpSyntax\Nodes\ArgumentNode;
use PhpSyntax\Nodes\ArrayItemNode;
use PhpSyntax\Nodes\ClosureUseNode;
use PhpSyntax\Nodes\DestructuringNode;
use PhpSyntax\Nodes\Expression\ArrayNode;
use PhpSyntax\Nodes\Expression\ArrowFunctionNode;
use PhpSyntax\Nodes\Expression\AssignmentNode;
use PhpSyntax\Nodes\Expression\ClosureNode;
use PhpSyntax\Nodes\Expression\EvalNode;
use PhpSyntax\Nodes\Expression\FunctionCallNode;
use PhpSyntax\Nodes\Expression\IncludeNode;
use PhpSyntax\Nodes\Expression\VariableNode;
use PhpSyntax\Nodes\ExpressionNode;
use PhpSyntax\Nodes\FunctionLikeNode;
use PhpSyntax\Nodes\Member\MethodNode;
use PhpSyntax\Nodes\ParameterNode;
use PhpSyntax\Nodes\Scalar\StringNode;
use PhpSyntax\Nodes\Statement\ForeachNode;
use PhpSyntax\Nodes\Statement\FunctionNode;
use PhpSyntax\Nodes\Statement\GlobalNode;
use PhpSyntax\Nodes\StaticVariableNode;
use PhpSyntax\Token;

/**
 * The variables of one scope: the body of a function, a method or a closure. An arrow function has no scope of its own
 * but for its parameters, its body reading the scope around it, and the `use` of a closure stands in the scope around
 * the closure. The top level of a file is a scope too, but none to judge: the file including it reads and writes its
 * variables, and so do functions through `global` and `$GLOBALS`, all out of sight of the run.
 */
final readonly class VariableScope
{
    public const array SCOPES = [FunctionNode::class, MethodNode::class, ClosureNode::class];

    public const array SUPER_GLOBALS = [
        'GLOBALS',
        '_SERVER',
        '_GET',
        '_POST',
        '_FILES',
        '_COOKIE',
        '_SESSION',
        '_REQUEST',
        '_ENV',
    ];

    private const array DYNAMIC_FUNCTIONS = ['compact' => true, 'extract' => true, 'get_defined_vars' => true];

    /**
     * The variables of each name and the `compact()` calls naming it, in source order.
     *
     * @var array<string, list<FunctionCallNode|VariableNode>>
     */
    private array $occurrences;

    /**
     * `$this`, the superglobals, the parameters, the captured variables and those declared `static` or `global`.
     *
     * @var array<string, true>
     */
    private array $bound;

    /** Whether the scope may reach a variable by a name it does not spell out. */
    private bool $dynamic;

    /**
     * @param array<string, list<FunctionCallNode|VariableNode>> $occurrences
     * @param array<string, true> $bound
     */
    private function __construct(array $occurrences, array $bound, bool $dynamic)
    {
        $this->occurrences = $occurrences;
        $this->bound = $bound;
        $this->dynamic = $dynamic;
    }

    /** @phpstan-assert-if-true ClosureNode|FunctionNode|MethodNode $node */
    public static function isScope(FunctionLikeNode|Node|Token|null $node): bool
    {
        return $node instanceof FunctionNode || $node instanceof MethodNode || $node instanceof ClosureNode;
    }

    public static function of(ClosureNode|FunctionNode|MethodNode $scope, RuleContext $context): self
    {
        $occurrences = [];
        $bound = array_fill_keys(['this', ...self::SUPER_GLOBALS], true);
        $dynamic = false;
        $nodes = $scope->find(
            ExpressionNode::class,
            static fn (ExpressionNode $node) => $node instanceof VariableNode || self::isDynamicAccess($node, $context)
        );

        foreach ($nodes as $node) {
            $name = $node instanceof VariableNode ? $node->plainName : null;

            if (self::findOwner($node, $name) !== $scope) {
                continue;
            }

            if ($node instanceof VariableNode && $name !== null) {
                if (self::isDeclaration($node)) {
                    $bound[$name] = true;
                } else {
                    $occurrences[$name][] = $node;
                }

                continue;
            }

            $compacted = $node instanceof FunctionCallNode ? self::findCompactedNames($node, $context) : null;

            if ($compacted === null) {
                $dynamic = true;

                continue;
            }

            foreach ($compacted as $compactedName) {
                $occurrences[$compactedName][] = $node;
            }
        }

        if ($scope instanceof ClosureNode) {
            $bound += array_fill_keys($scope->getCapturedVariableNames(), true);
        }

        return new self($occurrences, $bound, $dynamic);
    }

    /**
     * The scope the variable of the name belongs to where the node stands; null at the top level of a file and inside
     * a property hook.
     */
    public static function findScope(Node $node, string $name): ClosureNode|FunctionNode|MethodNode|null
    {
        $owner = self::findOwner($node, $name);

        return self::isScope($owner) ? $owner : null;
    }

    /**
     * Whether the variable is a place a value is written to: the target of a plain assignment, an item of
     * a destructuring target or the key or the value of a `foreach`; one bound by reference only with `$byReference`.
     */
    public static function isAssignmentTarget(VariableNode $variable, bool $byReference = false): bool
    {
        $parent = $variable->parent;

        return match (true) {
            $parent instanceof AssignmentNode => $parent->target === $variable,
            $parent instanceof ForeachNode => $parent->key === $variable
                || ($parent->value === $variable && ($byReference || $parent->ampersand === null)),
            $parent instanceof ArrayItemNode => $parent->value === $variable
                && ($byReference || $parent->ampersand === null)
                && self::isDestructuringTarget($parent),
            default => false,
        };
    }

    /**
     * The function, method, closure or property hook whose variable of the name the node is; null at the top level
     * of the file. An arrow function counts only where it declares a parameter of the name.
     */
    private static function findOwner(Node $node, ?string $name): ?FunctionLikeNode
    {
        $owner = $node->parent instanceof ClosureUseNode
            ? $node->findAncestor(ClosureNode::class)?->findAncestor(FunctionLikeNode::class)
            : $node->findAncestor(FunctionLikeNode::class);

        while ($owner instanceof ArrowFunctionNode && ($name === null || !self::declaresParameter($owner, $name))) {
            $owner = $owner->findAncestor(FunctionLikeNode::class);
        }

        return $owner;
    }

    private static function declaresParameter(ArrowFunctionNode $function, string $name): bool
    {
        return array_any(
            $function->parameters->getItems(),
            static fn (ParameterNode $parameter) => $parameter->variable->plainName === $name
        );
    }

    private static function isDeclaration(VariableNode $variable): bool
    {
        $parent = $variable->parent;
        $declarator = $parent instanceof ParameterNode || $parent instanceof StaticVariableNode;

        return ($declarator && $parent->variable === $variable) || $parent?->parent instanceof GlobalNode;
    }

    private static function isDestructuringTarget(ArrayItemNode $item): bool
    {
        $destructuring = $item->parent?->parent;
        $parent = $destructuring?->parent;

        return $destructuring instanceof DestructuringNode && match (true) {
            $parent instanceof AssignmentNode => $parent->target === $destructuring,
            $parent instanceof ForeachNode => $parent->value === $destructuring,
            $parent instanceof ArrayItemNode => self::isDestructuringTarget($parent),
            default => false,
        };
    }

    private static function isDynamicAccess(ExpressionNode $node, RuleContext $context): bool
    {
        return $node instanceof IncludeNode
            || $node instanceof EvalNode
            || ($node instanceof FunctionCallNode
                && GlobalCalls::findFunction($node, self::DYNAMIC_FUNCTIONS, $context) !== null);
    }

    /**
     * The names a `compact()` call reads where it names them with string literals; null for a call of another
     * function or with any other argument.
     *
     * @return list<string>|null
     */
    private static function findCompactedNames(FunctionCallNode $call, RuleContext $context): ?array
    {
        if (GlobalCalls::findFunction($call, ['compact' => true], $context) === null) {
            return null;
        }

        $names = [];

        foreach ($call->arguments->items->getItems() as $argument) {
            $literal = $argument instanceof ArgumentNode && $argument->ellipsis === null
                ? self::readLiteralNames($argument->value)
                : null;

            if ($literal === null) {
                return null;
            }

            $names = [...$names, ...$literal];
        }

        return $names;
    }

    /**
     * The names a string literal or an array of them holds; null for any other expression.
     *
     * @return list<string>|null
     */
    private static function readLiteralNames(ExpressionNode $expression): ?array
    {
        if ($expression instanceof StringNode) {
            return [$expression->toValue()];
        }

        if (!$expression instanceof ArrayNode) {
            return null;
        }

        $names = [];

        foreach ($expression->items->getItems() as $item) {
            $literal = $item instanceof ArrayItemNode
                && $item->key === null
                && $item->ellipsis === null
                && $item->value instanceof ExpressionNode
                ? self::readLiteralNames($item->value)
                : null;

            if ($literal === null) {
                return null;
            }

            $names = [...$names, ...$literal];
        }

        return $names;
    }

    /** @return array<string, list<FunctionCallNode|VariableNode>> */
    public function getOccurrences(): array
    {
        return $this->occurrences;
    }

    /** @return list<FunctionCallNode|VariableNode> */
    public function findOccurrences(string $name): array
    {
        return $this->occurrences[$name] ?? [];
    }

    public function isBound(string $name): bool
    {
        return isset($this->bound[$name]);
    }

    public function isDynamic(): bool
    {
        return $this->dynamic;
    }
}
