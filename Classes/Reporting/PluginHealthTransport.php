<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Reporting;

use Supertext\Typo3Translation\PluginHealth\Http\HttpRequest;
use Supertext\Typo3Translation\PluginHealth\Http\HttpResponse;
use Supertext\Typo3Translation\PluginHealth\Http\HttpTransport;
use Supertext\Typo3Translation\PluginHealth\Http\TransportException;
use Supertext\Typo3Translation\Sdk\Http\HttpRequest as SdkHttpRequest;
use Supertext\Typo3Translation\Sdk\Http\HttpTransport as SdkHttpTransport;

/**
 * Sends Supertext Plugin Health's version reports through the SDK's HttpTransport (TYPO3's
 * HTTP client), so reports and Supertext requests share one client. No TYPO3 classes.
 */
final class PluginHealthTransport implements HttpTransport
{
    public function __construct(private readonly SdkHttpTransport $transport) {}

    public function send(HttpRequest $request): HttpResponse
    {
        try {
            $response = $this->transport->send(new SdkHttpRequest($request->method, $request->url, $request->headers, $request->body, $request->timeout));
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
        return new HttpResponse($response->status, $response->body, $response->headers);
    }
}
