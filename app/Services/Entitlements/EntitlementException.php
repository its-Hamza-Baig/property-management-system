<?php

namespace App\Services\Entitlements;

use RuntimeException;

/**
 * Raised when an action is blocked by a plan limit. The handler turns this
 * into a plain message for the account holder rather than a 500 page, because
 * hitting a limit is a normal situation, not a bug.
 */
class EntitlementException extends RuntimeException
{
}
