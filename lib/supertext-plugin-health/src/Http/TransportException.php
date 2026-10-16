<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\PluginHealth\Http;

/**
 * No HTTP answer (thrown by CurlTransport; the reporter records a failed report).
 */
final class TransportException extends \RuntimeException
{
}
