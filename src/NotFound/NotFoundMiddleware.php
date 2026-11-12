<?php

namespace SeoKit\NotFound;

use Concrete\Core\Application\Application;
use Symfony\Component\HttpFoundation\Request;
use Concrete\Core\Http\Middleware\{DelegateInterface, MiddlewareInterface};

/**
 * Notes every GET or HEAD that ends in a 404. Runs after the response is built and fails open: a database problem
 * must never change what the visitor sees.
 */
class NotFoundMiddleware implements MiddlewareInterface
{
    public function __construct(protected Application $app) {}

    public function process(Request $request, DelegateInterface $frame)
    {
        $response = $frame->next($request);

        if ($response->getStatusCode() === 404 && in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            try {
                $path = method_exists($request, 'getPath') ? $request->getPath() : $request->getPathInfo();
                $this->app->make(NotFoundService::class)->record($path, $request->headers->get('Referer'));
            } catch (\Throwable) {
                // not worth a broken page
            }
        }

        return $response;
    }
}
