<?php declare(strict_types = 1);

namespace Brandejt\DressCodeStandard;

use Brandejt\DressCodeStandard\Rules\AttributesJoiningRule;
use Brandejt\DressCodeStandard\Rules\AttributesOrderRule;
use Brandejt\DressCodeStandard\Rules\BlankLinesAroundParentCallRule;
use Brandejt\DressCodeStandard\Rules\CatchExceptionsOrderRule;
use Brandejt\DressCodeStandard\Rules\ConstructorPropertyPromotionRule;
use Brandejt\DressCodeStandard\Rules\DeclareSpacingRule;
use Brandejt\DressCodeStandard\Rules\ImplicitArrayCreationRule;
use Brandejt\DressCodeStandard\Rules\InlineVarDeclarationRule;
use Brandejt\DressCodeStandard\Rules\LateStaticBindingForConstantsRule;
use Brandejt\DressCodeStandard\Rules\PhpdocAnnotationsApartRule;
use Brandejt\DressCodeStandard\Rules\ReturningReferenceRule;
use Brandejt\DressCodeStandard\Rules\SelfReferenceRule;
use Brandejt\DressCodeStandard\Rules\SuperGlobalsRule;
use Brandejt\DressCodeStandard\Rules\ThrowsAnnotationsOrderRule;
use Brandejt\DressCodeStandard\Rules\TrailingCommaInCallRule;
use Brandejt\DressCodeStandard\Rules\UnusedClosureUseRule;
use Brandejt\DressCodeStandard\Rules\UnusedVariableRule;
use Brandejt\DressCodeStandard\Rules\UselessParenthesesRule;
use Brandejt\DressCodeStandard\Rules\UselessVariableRule;
use Brandejt\DressCodeStandard\Rules\VariableVariablesRule;
use DressCode\Plugin as DressCodePlugin;
use DressCode\PluginManifest;

final class Plugin implements DressCodePlugin
{
    public function getManifest(): PluginManifest
    {
        return new PluginManifest(
            rules: [
                AttributesJoiningRule::class,
                AttributesOrderRule::class,
                BlankLinesAroundParentCallRule::class,
                CatchExceptionsOrderRule::class,
                ConstructorPropertyPromotionRule::class,
                DeclareSpacingRule::class,
                ImplicitArrayCreationRule::class,
                InlineVarDeclarationRule::class,
                LateStaticBindingForConstantsRule::class,
                PhpdocAnnotationsApartRule::class,
                ReturningReferenceRule::class,
                SelfReferenceRule::class,
                SuperGlobalsRule::class,
                ThrowsAnnotationsOrderRule::class,
                TrailingCommaInCallRule::class,
                UnusedClosureUseRule::class,
                UnusedVariableRule::class,
                UselessParenthesesRule::class,
                UselessVariableRule::class,
                VariableVariablesRule::class,
            ],
            presets: [
                'brandejt/style' => __DIR__ . '/../presets/style.neon',
                'brandejt/rules' => __DIR__ . '/../presets/rules.neon',
            ],
            section: 'brandejt'
        );
    }
}
