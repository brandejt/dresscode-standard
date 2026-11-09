<?php declare(strict_types = 1);

use Brandejt\DressCodeStandard\Rules\TrailingCommaInCallRule;
use DressCode\Testing\RuleTester;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

Assert::noError(static fn () => RuleTester::run(
    TrailingCommaInCallRule::class,
    __DIR__ . '/../fixtures/trailingCommaInCall'
));
