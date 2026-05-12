<?php
declare(strict_types=1);

namespace App\Controller;

use App\Service\GitInfoService;
use Authentication\Controller\Component\AuthenticationComponent;
use Authorization\Controller\Component\AuthorizationComponent;
use Cake\Controller\Component\FlashComponent;
use Cake\Controller\Controller;
use Cake\Event\EventInterface;

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

    /**
     * Injects the current git commit info into every view so the layout can
     * display the deployed code version in the sidebar.
     */
    public function beforeRender(EventInterface $event): void
    {
        parent::beforeRender($event);

        $gitInfo = (new GitInfoService())->head();
        $this->set('gitHash', $gitInfo['hash']);
        $this->set('gitMessage', $gitInfo['message']);
    }
}
