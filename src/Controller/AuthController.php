<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * Handles web-facing login/logout pages.
 * API authentication is handled by App\Controller\Api\V1\AuthController.
 */
class AuthController extends AppController
{
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);
        $this->Authentication->allowUnauthenticated(['login']);
    }

    /**
     * GET /login — display login page
     */
    public function login(): ?Response
    {
        $this->viewBuilder()->setLayout('auth');

        if ($this->Authentication->getResult()?->isValid()) {
            return $this->redirect('/dashboard');
        }

        return null;
    }

    /**
     * POST /auth/logout
     */
    public function logout(): Response
    {
        $this->Authentication->logout();
        /** @var Response */
        return $this->redirect('/login');
    }
}
