<?php
declare(strict_types=1);

namespace App\Error;

use Cake\Error\Renderer\WebExceptionRenderer;
use Cake\Http\Exception\HttpException;
use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Exception renderer that returns JSON for `/api/*` paths and HTML elsewhere.
 *
 * Issue #9 review (PR #31) reported the frontend choking on "Unexpected
 * token '<', '<!DOCTYPE ...'" because uncaught exceptions in API
 * controllers (typically ForbiddenException, UnauthorizedException,
 * NotFoundException, MissingControllerException) were rendered through
 * the default HTML error template even though the Vue layer always sets
 * `Accept: application/json` and expects a structured payload.
 *
 * This renderer detects `/api/` in the request path and bypasses the
 * default WebExceptionRenderer template machinery entirely — it emits
 * a hand-built JSON body in the application's canonical envelope:
 *
 *   { "success": false, "data": null, "errors": ["<message>"], "meta": {} }
 *
 * Bypassing the parent template path matters because in debug mode the
 * default renderer hard-codes HTML output for several exception types
 * (notably MissingControllerException), regardless of the configured
 * view. Non-API routes fall through to the framework default and
 * continue to render the standard HTML error page.
 */
class ApiAwareExceptionRenderer extends WebExceptionRenderer
{
    public function render(): ResponseInterface
    {
        if (!$this->isApiRequest()) {
            return parent::render();
        }

        return $this->renderJson($this->error);
    }

    private function renderJson(Throwable $error): Response
    {
        $code = $this->getHttpCode($error);
        $message = $error->getMessage() !== '' ? $error->getMessage() : 'Internal error';

        $payload = [
            'success' => false,
            'data' => null,
            'errors' => [$message],
            'meta' => [],
        ];

        $encoded = json_encode($payload);
        if ($encoded === false) {
            $encoded = '{"success":false,"data":null,"errors":["Internal error"],"meta":{}}';
        }

        /** @var \Cake\Http\Response $response */
        $response = $this->controller->getResponse();
        $response = $response
            ->withStatus($code)
            ->withType('application/json')
            ->withStringBody($encoded);

        if ($error instanceof HttpException) {
            foreach ($error->getHeaders() as $name => $value) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    private function isApiRequest(): bool
    {
        $request = $this->controller->getRequest();
        $path = (string)$request->getUri()->getPath();

        return str_contains($path, '/api/');
    }
}
