<?php

declare(strict_types=1);

namespace Maispace\MaiEvents\Middleware;

use Maispace\MaiEvents\Controller\EventsController;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Keeps calendar navigation requests out of the page cache.
 *
 * The view/date parameters are excluded from cHash validation, so the page cache
 * identifier is the same with and without them. Without this middleware a cache
 * hit would serve the default calendar and silently ignore the requested month,
 * and a cache miss would store the navigated variant for every visitor.
 * The cache has to be bypassed before the lookup, which only a middleware can do.
 */
final readonly class BypassPageCacheForCalendarNavigationMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        foreach (EventsController::NAVIGATION_PARAMETERS as $parameter) {
            if (array_key_exists($parameter, $queryParams)) {
                $cacheInstruction = $request->getAttribute('frontend.cache.instruction', new CacheInstruction());
                $cacheInstruction->disableCache('mai_events: calendar navigation parameter ' . $parameter);

                return $handler->handle($request->withAttribute('frontend.cache.instruction', $cacheInstruction));
            }
        }

        return $handler->handle($request);
    }
}
