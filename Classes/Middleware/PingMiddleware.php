<?php

declare(strict_types=1);

namespace Supertext\Typo3Translation\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Supertext\Typo3Translation\Configuration\ExtensionSettings;
use Supertext\Typo3Translation\Reporting\Reporting;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\Response;

/**
 * The public ping endpoint of the Supertext Commando Center: GET <installation URL>supertext/ping?challenge=…
 *
 * Runs in the frontend stack before the site resolver, so it needs no login, session or
 * CSRF token and never touches the page cache. The answer (status, headers, body) is the
 * SDK's pingResponse(): 200 with the challenge, 400 for a bad one, 404 while version
 * reports are off.
 */
final class PingMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly ExtensionSettings $settings) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request->getMethod() !== 'GET' || !$this->isPingPath($request)) {
            return $handler->handle($request);
        }
        $challenge = $request->getQueryParams()['challenge'] ?? null;
        $ping = Reporting::ping(is_string($challenge) ? $challenge : null, $this->settings->areVersionReportsEnabled());
        $response = new Response('php://temp', $ping->status, $ping->headers);
        $response->getBody()->write($ping->body);
        return $response;
    }

    private function isPingPath(ServerRequestInterface $request): bool
    {
        $params = $request->getAttribute('normalizedParams');
        $root = $params instanceof NormalizedParams ? $params->getSitePath() : '/';
        $path = $request->getUri()->getPath();
        return $path === rtrim($root, '/') . '/' . Reporting::PING_PATH;
    }
}
