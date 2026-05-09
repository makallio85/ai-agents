<?php
declare(strict_types=1);

namespace App\Controller;

class LogsController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }
}
