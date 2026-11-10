<?php

declare(strict_types=1);

namespace DevnullInsights;

use RuntimeException;

/** A failure the user must see: an unreachable site, a changed response shape, or an invalid input. */
final class DevnullException extends RuntimeException
{
}
