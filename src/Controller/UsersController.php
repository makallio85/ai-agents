<?php
declare(strict_types=1);

namespace App\Controller;

/**
 * Renders the Users admin page under "User Management".
 *
 * The page is a Vue shell that lists all users (any approval state) and
 * lets administrators inspect/manage them. It is separate from
 * MessagingRequests, which is scoped to the guest-approval triage flow.
 *
 * Added for the nav restructure in issue #14: the new "User Management"
 * section needs a top-level Users entry pointing at /users.
 */
class UsersController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }
}
