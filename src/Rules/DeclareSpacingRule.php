<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard\Rules;

use DressCode\Claim;
use DressCode\Decision;
use DressCode\Domains\Shapes;
use DressCode\GapRule;
use DressCode\RuleInfo;
use DressCode\Stage;
use PhpSyntax\Nodes\DeclareItemNode;
use PhpSyntax\Nodes\Statement\DeclareNode;

#[RuleInfo(Stage::Formatting)]
final class DeclareSpacingRule extends GapRule
{
    /** @return list<Decision> */
    public static function getDecisions(): array
    {
        return [
            new Decision(
                'brandejt.declareSpacing',
                new Shapes(['spaced' => ['declare(strict_types = 1)', 'a space around `=`, none elsewhere inside']]),
                'The whitespace inside a `declare` statement'
            ),
        ];
    }

    /** @return array<string, array<string, array{Claim|null, Claim|null}>> */
    public function getClaims(): array
    {
        return [
            DeclareNode::class => [
                'declareKeyword' => [null, Claim::noSpace()],
                'openParen' => [null, Claim::noSpace()],
                'closeParen' => [Claim::noSpace(), null],
            ],
            DeclareItemNode::class => [
                'name' => [null, Claim::singleSpace()],
                'equals' => [Claim::singleSpace(), Claim::singleSpace()],
            ],
        ];
    }
}
