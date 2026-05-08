<?php
declare(strict_types=1);

namespace App\Controller\Api\V1;

class LabelsController extends AppController
{
    public function index(): void
    {
        $this->requirePermission('labels', 'read');
        $labels = $this->fetchTable('Labels')->find()->all()->toList();
        $this->success($labels, ['count' => count($labels)]);
    }

    public function view(int $id): void
    {
        $this->requirePermission('labels', 'read');
        $label = $this->fetchTable('Labels')->find()->where(['Labels.id' => $id])->first();

        if ($label === null) {
            $this->error('Label not found', [], 404);
            return;
        }
        $this->success($label);
    }

    public function create(): void
    {
        $this->requirePermission('labels', 'create');
        $labels = $this->fetchTable('Labels');
        $entity = $labels->newEntity($this->request->getData());
        if (!$labels->save($entity)) {
            $this->error('Validation failed', array_keys($entity->getErrors()), 422);
            return;
        }
        $this->success($entity, [], 201);
    }

    public function update(int $id): void
    {
        $this->requirePermission('labels', 'update');
        $labels = $this->fetchTable('Labels');
        $entity = $labels->find()->where(['Labels.id' => $id])->first();
        if ($entity === null) {
            $this->error('Label not found', [], 404);
            return;
        }
        $labels->patchEntity($entity, $this->request->getData());
        if (!$labels->save($entity)) {
            $this->error('Validation failed', array_keys($entity->getErrors()), 422);
            return;
        }
        $this->success($entity);
    }

    public function delete(int $id): void
    {
        $this->requirePermission('labels', 'delete');
        $entity = $this->fetchTable('Labels')->find()->where(['Labels.id' => $id])->first();
        if ($entity === null) {
            $this->error('Label not found', [], 404);
            return;
        }
        $this->fetchTable('Labels')->delete($entity);
        $this->success(null, [], 204);
    }
}
