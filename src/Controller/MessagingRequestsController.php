<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Admin page for triaging incoming messaging requests from external channels
 * (WhatsApp, Slack, future channels).
 *
 * Renders an empty Vue mount point — all data and actions are pulled from
 * /api/v1/users (and the approve/reject endpoints) by the page's Vue app.
 * Permission gating happens server-side on the API; this controller just
 * delivers the shell HTML and only requires an authenticated session to
 * reach the view.
 *
 * Previously named MessagingGuestsController; renamed for the navigation
 * restructure that groups this under User Management as
 * "Messaging Requests" (issue #14).
 */
class MessagingRequestsController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }
}
