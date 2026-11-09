<?php declare(strict_types = 1);

use Brandejt\DressCodeStandard\Rules\VariableVariablesRule;
use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::noError(static fn () => RuleTester::run(
    VariableVariablesRule::class,
    __DIR__ . '/../fixtures/variableVariables'
));
