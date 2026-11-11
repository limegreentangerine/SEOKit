<?php

namespace SeoKit\Robots;

use Concrete\Core\Http\Response;
use Concrete\Core\Support\Facade\Application;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Serves /llms.txt when it is switched on. A physical llms.txt in the web root is served by the web server first.
 */
class LlmsController
{
    public function serve(): HttpResponse
    {
        try {
            $service = Application::getFacadeApplication()->make(AiCrawlerService::class);

            if ($service->settings()['llms']) {
                return new Response($service->llms(), 200, [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Cache-Control' => 'public, max-age=300',
                ]);
            }
        } catch (\Throwable) {
            // answer as if there were no file rather than showing an error page
        }

        return new Response('Not found', 404, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
