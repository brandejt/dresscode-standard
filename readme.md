DressCode Standard
==================

A coding standard for [DressCode](https://dresscode.run): a plugin with twenty rules of its own and two presets.
`brandejt/style` is [PER Coding Style](https://www.php-fig.org/per/coding-style/) with a stricter layout;
`brandejt/rules` says what the code does, whatever it looks like. A project takes one or both.

Installation
------------

```shell
composer require --dev brandejt/dresscode-standard
```

It needs PHP 8.4 or newer and DressCode 1.3 or newer. The code it checks may be written for any PHP from 8.0 on:
DressCode writes only the syntax the `composer.json` of the project allows.

Configuration
-------------

`dresscode.neon` of the project:

```neon
use:
    - brandejt/dresscode-standard   # lets the plugin in, with its rules and presets
    - brandejt/style
    - brandejt/rules

paths:
    - src
    - tests

typeAnalysis: phpstan   # the decisions that need the types of the code run with the PHPStan of the project
```

The project has the last word on every key, in its file or under `overrides` for a part of the code:

```neon
overrides:
    -
        paths: [src/Http/Request.php]
        # the request is read here and nowhere else
        brandejt:
            superGlobals: keep
```

One line is silenced with a comment, `// dresscode:ignore brandejt.superGlobals -- <why>`.

Presets
-------

**`brandejt/style`** builds on `perCs` and decides how the code looks: the braces of a multiline signature on a line
of their own, empty bodies expanded, `declare(strict_types = 1)` on the line of the opening tag, no padding inside
brackets and no alignment, a concatenation operator opening the line, imports grouped by kind and sorted by name,
global classes imported and global functions written bare, one order of the members of a class, `T|null` in a doc
comment, single quotes, and the layout the rules of the plugin decide.

**`brandejt/rules`** decides what the code does: hygiene (`count()`, not `sizeof()`; `$f($x)`, not
`call_user_func()`), the mistakes DressCode knows, control flow without `else` after `return`, strict comparison,
native types on every parameter, property, return and constant, doc comments that say something the code does not
and carry the item types of arrays, the constructs of the PHP the project allows and the deprecations it meets
(`upgrading`), and the rules of the plugin that are no matter of layout.

Rules
-----

Every rule decides under the section `brandejt`. The rules that say "reported" change no code.

| Decision | What it decides | Fix |
|---|---|---|
| `attributesJoining` | Several attributes in one group `#[A, B]` | reported |
| `attributesOrder` | The order of the attribute groups of a declaration, by the name of their first attributes as written | sorted |
| `blankLinesAroundParentCall` | Blank lines before a statement calling a parent method or constructor that is not the first of its block, and after one that is not the last | fixed |
| `catchExceptionsOrder` | The order of the types of a multi-catch `catch (A \| B $e)`, alphabetical regardless of letter case | sorted |
| `constructorPropertyPromotion` | A constructor parameter promoted to a property | reported |
| `declareSpacing` | The whitespace inside a `declare` statement, `declare(strict_types = 1)` | fixed |
| `implicitArrayCreation` | An element assigned to a variable nothing created before, `$a[] = 1` without `$a = []` | reported |
| `inlineVarDeclaration` | An inline `@var` written `@var Type $x` above the statement that assigns `$x`, or first in the body of the loop that does | fixed |
| `lateStaticBindingForConstants` | A class constant fetched with `static::`, which is written `self::` | fixed, risky outside a final class |
| `phpdocAnnotationsApart` | A blank line between the description and the annotations of a doc comment, and none between two annotations | fixed |
| `returningReference` | A function returning a reference, `function &find()` | reported |
| `selfReference` | How a class refers to itself where `qualification.currentClass` does not look: in a type declaration, and in an interface or an enum also in an expression | fixed |
| `superGlobals` | A superglobal variable such as `$_GET` or `$_SERVER` | reported |
| `throwsAnnotationsOrder` | The order of the `@throws` annotations of a function, a method or a closure, by the first exception type of each | sorted |
| `trailingCommaInCall` | A comma after the last argument of a call, an instantiation, an attribute, `isset()` or `unset()` | removed |
| `unusedClosureUse` | A variable captured by the `use` of a closure whose body never uses it | removed from the `use` |
| `unusedVariable` | A variable that its function only assigns and never reads | reported |
| `uselessParentheses` | Parentheses that change nothing, `foo(($a))`, `$x = ($a + $b);` or `($a) ? $b : $c` | removed |
| `uselessVariable` | A variable assigned only to be returned by the next statement, `$a = f(); return $a;` | written `return f();` |
| `variableVariables` | A variable named by an expression, `$$a`, `${expr}` or `A::$$b` | reported |

`dresscode explain brandejt` lists them with their values in the project.

Development
-----------

```shell
composer install
vendor/bin/tester -s tests/Cases
vendor/bin/dresscode check
vendor/bin/phpstan analyse
```

Every rule is tested with `DressCode\Testing\RuleTester` over the fixtures in `tests/fixtures/<decision>`: `*.code`
is the input, `*.expected` the output after the fix where the rule fixes, `*.violations` what it reports and
`values.neon` the values of the decisions.

License
-------

MIT. `DeclareSpacingRule` is derived from the rule of the same name in DressCode, Copyright (c) David Grudl, MIT.
