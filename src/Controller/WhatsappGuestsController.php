<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Admin page for triaging WhatsApp guest users.
 *
 * Renders an empty Vue mount point — all data and actions are pulled from
 * /api/v1/users (and the approve/reject endpoints) by the page's Vue app.
 * Permission gating happens server-side on the API; this controller just
 * delivers the shell HTML and only requires an authenticated session to
 * reach the view.
 */
class WhatsappGuestsController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }
}
