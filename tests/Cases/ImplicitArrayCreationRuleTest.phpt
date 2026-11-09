<?php declare(strict_types = 1);

use Brandejt\DressCodeStandard\Rules\ImplicitArrayCreationRule;
use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::noError(static fn () => RuleTester::run(
    ImplicitArrayCreationRule::class,
    __DIR__ . '/../fixtures/implicitArrayCreation'
));
