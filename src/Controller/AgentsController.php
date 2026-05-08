<?php
declare(strict_types=1);

namespace App\Controller;

class AgentsController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }

    public function view(int $id): void
    {
        $this->viewBuilder()->setLayout('app');
        $this->set('agentId', $id);
    }
}
