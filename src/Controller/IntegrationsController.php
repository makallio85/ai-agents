<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Renders the Integrations index page.
 *
 * Acts as the landing page under the new "Integrations" top-level nav
 * section introduced in issue #14. Today it surfaces only the GitHub
 * integration (with Labels nested underneath); the layout is designed so
 * future integrations (Jira, GitLab, …) can drop in without changing the
 * nav skeleton. Per the resolved spec, Slack and WhatsApp are NOT
 * treated as integrations here — they are per-agent channels configured
 * on the agent view page.
 *
 * Pure shell controller: no data loaded server-side; the template links
 * directly to each integration's own page.
 */
class IntegrationsController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }
}
