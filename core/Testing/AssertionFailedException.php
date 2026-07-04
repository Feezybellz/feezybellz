<?php

namespace Framework\Core\Testing;

/**
 * Thrown when an assertion fails. The runner classifies this as a
 * "failure" (a test that ran but did not meet expectations) as opposed
 * to an "error" (an unexpected exception in the code under test).
 */
class AssertionFailedException extends \Exception
{
}
