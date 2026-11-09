<?php declare(strict_types = 1);

use Brandejt\DressCodeStandard\Rules\UnusedVariableRule;
use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::noError(static fn () => RuleTester::run(
    UnusedVariableRule::class,
    __DIR__ . '/../fixtures/unusedVariable'
));
