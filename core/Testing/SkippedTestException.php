<?php

namespace Framework\Core\Testing;

/**
 * Thrown by markTestSkipped(). The runner counts these separately and
 * never reports them as failures.
 */
class SkippedTestException extends \Exception
{
}
