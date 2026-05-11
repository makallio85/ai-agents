<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Renders the authenticated user's profile page.
 *
 * Data is loaded and saved client-side via /api/v1/profile endpoints.
 */
class ProfileController extends AppController
{
    /**
     * GET /profile
     */
    public function index(): void
    {
        $this->set('title', 'My Profile');
    }
}
