<?php
declare(strict_types=1);

namespace App\Controller;

use Authentication\Controller\Component\AuthenticationComponent;
use Authorization\Controller\Component\AuthorizationComponent;
use Cake\Controller\Component\FlashComponent;
use Cake\Controller\Controller;

/**
 * @property AuthenticationComponent $Authentication
 * @property AuthorizationComponent $Authorization
 * @property FlashComponent $Flash
 */
class AppController extends Controller
{
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('Authentication.Authentication');
        $this->loadComponent('Authorization.Authorization');
        $this->loadComponent('Flash');
    }
}
