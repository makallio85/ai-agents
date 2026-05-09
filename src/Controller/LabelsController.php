<?php
declare(strict_types=1);

namespace App\Controller;

class LabelsController extends AppController
{
    public function index(): void
    {
        $this->viewBuilder()->setLayout('app');
    }
}
