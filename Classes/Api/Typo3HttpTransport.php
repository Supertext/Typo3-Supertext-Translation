<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Api;

use Supertext\Typo3Translation\Sdk\Http\HttpRequest;
use Supertext\Typo3Translation\Sdk\Http\HttpResponse;
use Supertext\Typo3Translation\Sdk\Http\HttpTransport;
use Supertext\Typo3Translation\Sdk\Http\TransportException;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * Sends the Supertext SDK's requests through TYPO3's HTTP client, so the proxy, TLS
 * and timeout settings of $GLOBALS['TYPO3_CONF_VARS']['HTTP'] apply.
 */
final class Typo3HttpTransport implements HttpTransport
{
    public function __construct(private readonly RequestFactory $requestFactory) {}

    public function send(HttpRequest $request): HttpResponse
    {
        $options = [
            'headers' => $request->headers,
            'http_errors' => false,
            'timeout' => $request->timeout,
        ];
        if ($request->body !== null) {
            $options['body'] = $request->body;
        }
        try {
            $response = $this->requestFactory->request($request->url, $request->method, $options);
        } catch (\Throwable $e) {
            throw new TransportException($e->getMessage(), 0, $e);
        }
        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[(string)$name] = implode(', ', $values);
        }
        return new HttpResponse($response->getStatusCode(), (string)$response->getBody(), $headers);
    }
}
