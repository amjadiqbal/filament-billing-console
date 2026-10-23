<?php

declare(strict_types=1);

namespace AmjadIqbal\FilamentBillingConsole\Support;

use RuntimeException;

/**
 * A sync that was refused before anything was stored. The message is safe to show.
 */
class SyncException extends RuntimeException {}
