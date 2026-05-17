<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Cake\Utility\Text;
use Cake\Validation\Validator;

class AgentsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('agents');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp');
        $this->hasMany('AgentContexts', ['foreignKey' => 'agent_id', 'dependent' => true]);
        $this->hasMany('AgentLogs', ['foreignKey' => 'agent_id']);
        $this->hasMany('ExecutionHistory', ['foreignKey' => 'agent_id']);
        $this->hasMany('PromptVersions', ['foreignKey' => 'agent_id', 'dependent' => true]);
        $this->hasMany('Conversations', ['foreignKey' => 'agent_id']);
        $this->hasMany('ChatSessions', ['foreignKey' => 'agent_id']);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator->notEmptyString('name')->maxLength('name', 150);
        // `slug` is auto-derived from `name` in beforeMarshal when the caller
        // omits it, so the validator only enforces shape, not presence.
        $validator->maxLength('slug', 150)->allowEmptyString('slug');
        $validator->notEmptyString('plugin')->maxLength('plugin', 150);
        $validator->boolean('is_enabled');
        return $validator;
    }

    /**
     * Auto-derive `slug` from `name` when the caller does not supply one.
     *
     * The UI form posts only `{name, description, plugin}` and `slug` has no
     * DB default, so without this hook every UI-driven create fails with
     * SQLSTATE 1364. Collisions with existing slugs are resolved by suffixing
     * `-2`, `-3`, ... so the unique index on `agents.slug` is respected.
     *
     * @param \Cake\Event\EventInterface<\App\Model\Table\AgentsTable> $event
     * @param \ArrayObject<string, mixed> $data
     * @param \ArrayObject<string, mixed> $options
     */
    public function beforeMarshal(EventInterface $event, ArrayObject $data, ArrayObject $options): void
    {
        $hasSlug = isset($data['slug']) && is_string($data['slug']) && trim($data['slug']) !== '';
        if ($hasSlug) {
            return;
        }

        $name = isset($data['name']) && is_string($data['name']) ? trim($data['name']) : '';
        if ($name === '') {
            return;
        }

        $data['slug'] = $this->generateUniqueSlug($name);
    }

    /**
     * @param \Cake\ORM\Query\SelectQuery<\App\Model\Entity\Agent> $query
     * @return \Cake\ORM\Query\SelectQuery<\App\Model\Entity\Agent>
     */
    public function findEnabled(SelectQuery $query): SelectQuery
    {
        return $query->where(['Agents.is_enabled' => true]);
    }

    /**
     * Build a URL-safe slug from `$name` and append `-N` until it is unique in
     * the `agents.slug` column.
     */
    private function generateUniqueSlug(string $name): string
    {
        $base = strtolower(Text::slug($name, '-'));
        if ($base === '') {
            $base = 'agent';
        }

        $candidate = $base;
        $suffix = 2;
        while ($this->exists(['slug' => $candidate])) {
            $candidate = $base . '-' . $suffix;
            $suffix++;
        }

        return $candidate;
    }
}
