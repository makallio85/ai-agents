<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Controller\AppController as BaseController;
use App\Model\Entity\User;
use Cake\Http\Response;

class AppController extends BaseController
{
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName('Json');
    }

    /**
     * Return structured success response.
     *
     * @param mixed $data
     * @param array<string, mixed> $meta
     */
    protected function success(mixed $data = null, array $meta = [], int $code = 200): Response
    {
        $this->set([
            'success' => true,
            'data' => $data,
            'errors' => [],
            'meta' => $meta,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'errors', 'meta']);
        return $this->response->withStatus($code);
    }

    /**
     * Return structured error response.
     *
     * @param list<string> $errors
     */
    protected function error(string $message, array $errors = [], int $code = 400): Response
    {
        $this->set([
            'success' => false,
            'data' => null,
            'errors' => array_merge([$message], $errors),
            'meta' => [],
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'data', 'errors', 'meta']);
        return $this->response->withStatus($code);
    }

    protected function getCurrentUser(): ?User
    {
        /** @var User|null */
        return $this->Authentication->getIdentity()?->getOriginalData();
    }

    protected function requirePermission(string $module, string $action): void
    {
        $user = $this->getCurrentUser();
        if ($user === null) {
            throw new \Cake\Http\Exception\UnauthorizedException('Not authenticated');
        }

        $policy = new \App\Authorization\RbacPolicy();
        if (!$policy->can($user, $module, $action)) {
            throw new \Cake\Http\Exception\ForbiddenException("Permission denied: {$module}.{$action}");
        }
    }
}
