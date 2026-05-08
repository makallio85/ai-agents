<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Serves the chat interface shell page.
 *
 * All data loading and interaction is handled client-side by the Vue 3 app
 * mounted on #chat-app via the API. This controller only renders the
 * page skeleton and passes the Vue script tag.
 */
class ChatController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }
}
