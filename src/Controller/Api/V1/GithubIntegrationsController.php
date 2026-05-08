<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

class GithubIntegrationsController extends AppController
{
    /**
     * GET /api/v1/github-integrations
     */
    public function index(): void
    {
        $this->requirePermission('github_integrations', 'read');
        $user = $this->getCurrentUser();

        $integrations = $this->fetchTable('GithubIntegrations')
            ->find('activeByUser', userId: $user->id)
            ->all()
            ->toList();

        $this->success($integrations, ['count' => count($integrations)]);
    }

    /**
     * GET /api/v1/github-integrations/view/:id
     */
    public function view(int $id): void
    {
        $this->requirePermission('github_integrations', 'read');
        $user = $this->getCurrentUser();

        $integration = $this->fetchTable('GithubIntegrations')
            ->find()
            ->where(['GithubIntegrations.id' => $id, 'GithubIntegrations.user_id' => $user->id])
            ->first();

        if ($integration === null) {
            $this->error('Integration not found', [], 404);
            return;
        }

        $this->success($integration);
    }

    /**
     * POST /api/v1/github-integrations/create
     */
    public function create(): void
    {
        $this->requirePermission('github_integrations', 'create');
        $user = $this->getCurrentUser();
        $data = $this->request->getData();
        $data['user_id'] = $user->id;

        $integrations = $this->fetchTable('GithubIntegrations');
        $entity = $integrations->newEntity($data);

        if (!$integrations->save($entity)) {
            $this->error('Validation failed', array_keys($entity->getErrors()), 422);
            return;
        }

        $this->success($entity, [], 201);
    }

    /**
     * PUT /api/v1/github-integrations/update/:id
     */
    public function update(int $id): void
    {
        $this->requirePermission('github_integrations', 'update');
        $user = $this->getCurrentUser();

        $integrations = $this->fetchTable('GithubIntegrations');
        $entity = $integrations->find()->where(['id' => $id, 'user_id' => $user->id])->first();

        if ($entity === null) {
            $this->error('Integration not found', [], 404);
            return;
        }

        $integrations->patchEntity($entity, $this->request->getData());
        if (!$integrations->save($entity)) {
            $this->error('Validation failed', array_keys($entity->getErrors()), 422);
            return;
        }

        $this->success($entity);
    }

    /**
     * DELETE /api/v1/github-integrations/delete/:id
     */
    public function delete(int $id): void
    {
        $this->requirePermission('github_integrations', 'delete');
        $user = $this->getCurrentUser();

        $integrations = $this->fetchTable('GithubIntegrations');
        $entity = $integrations->find()->where(['id' => $id, 'user_id' => $user->id])->first();

        if ($entity === null) {
            $this->error('Integration not found', [], 404);
            return;
        }

        $integrations->delete($entity);
        $this->success(null, [], 204);
    }
}
