<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateIssueParsingJobs extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/5/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('issue_parsing_jobs');
        $table->addColumn('conversation_id', 'integer', ['null' => false])
              ->addColumn('agent_id', 'integer', ['null' => false])
              ->addColumn('execution_id', 'string', ['limit' => 36, 'null' => true, 'default' => null, 'comment' => 'UUID'])
              ->addColumn('raw_block', 'text', ['null' => false, 'limit' => \Migrations\Db\Adapter\MysqlAdapter::TEXT_LONG, 'comment' => 'Original issue block text'])
              ->addColumn('parsed_data', 'text', ['null' => true, 'default' => null, 'comment' => 'JSON of ParsedIssueDto'])
              ->addColumn('status', 'string', ['limit' => 30, 'null' => false, 'default' => 'pending', 'comment' => 'pending|validating|creating|completed|failed'])
              ->addColumn('github_issue_number', 'integer', ['null' => true, 'default' => null])
              ->addColumn('github_issue_url', 'string', ['limit' => 500, 'null' => true, 'default' => null])
              ->addColumn('applied_labels', 'text', ['null' => true, 'default' => null, 'comment' => 'JSON array of label slugs'])
              ->addColumn('error_message', 'text', ['null' => true, 'default' => null])
              ->addColumn('attempts', 'integer', ['null' => false, 'default' => 0])
              ->addColumn('created', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addColumn('modified', 'datetime', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
              ->addIndex(['conversation_id'], ['name' => 'idx_issue_parsing_jobs_conversation_id'])
              ->addIndex(['agent_id'], ['name' => 'idx_issue_parsing_jobs_agent_id'])
              ->addIndex(['status'], ['name' => 'idx_issue_parsing_jobs_status'])
              ->addIndex(['execution_id'], ['name' => 'idx_issue_parsing_jobs_execution_id'])
              ->addForeignKey('conversation_id', 'conversations', 'id', ['delete' => 'CASCADE', 'update' => 'CASCADE'])
              ->addForeignKey('agent_id', 'agents', 'id', ['delete' => 'RESTRICT', 'update' => 'CASCADE'])
              ->create();
    }
}
