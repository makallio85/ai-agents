<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Renders settings pages.
 *
 * Each action returns an HTML shell that mounts the corresponding Vue app.
 * All data is fetched client-side via the JSON API so these actions stay thin.
 */
class SettingsController extends AppController
{
    /**
     * GET /settings/permissions
     *
     * Permission matrix editor — allows administrators and superusers to
     * view and edit which module/action pairs are granted to each role.
     */
    public function permissions(): void
    {
        $this->viewBuilder()->setLayout('app');
        $this->set('title', 'Permissions');
    }
}
