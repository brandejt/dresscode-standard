<?php declare(strict_types = 1);

use Brandejt\DressCodeStandard\Rules\ThrowsAnnotationsOrderRule;
use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::noError(static fn () => RuleTester::run(
    ThrowsAnnotationsOrderRule::class,
    __DIR__ . '/../fixtures/throwsAnnotationsOrder'
));
