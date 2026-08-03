<?php

use PHPUnit\Framework\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Plain PHPUnit base — this package has no framework, so tests bind to the
| default TestCase.
|
*/

pest()->extend(TestCase::class)->in('Unit');
