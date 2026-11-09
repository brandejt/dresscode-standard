<?php declare(strict_types = 1);

use Brandejt\DressCodeStandard\Rules\UselessParenthesesRule;
use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::noError(static fn () => RuleTester::run(
    UselessParenthesesRule::class,
    __DIR__ . '/../fixtures/uselessParentheses'
));
