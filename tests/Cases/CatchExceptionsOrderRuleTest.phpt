<?php declare(strict_types = 1);

use Brandejt\DressCodeStandard\Rules\CatchExceptionsOrderRule;
use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::noError(static fn () => RuleTester::run(
    CatchExceptionsOrderRule::class,
    __DIR__ . '/../fixtures/catchExceptionsOrder'
));
