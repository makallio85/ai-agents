<?php
declare(strict_types=1);

namespace App\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;

/**
 * Resets the administrator account to its default credentials.
 *
 * Useful during development to recover access when locked out or when
 * the admin password has been lost. Targets the user with email
 * admin@ai-agents.local (created by AdminUserSeed).
 *
 * Resets username, password, and ensures the account is active and approved.
 * The bcrypt hash is applied automatically via the User entity mutator.
 *
 * Usage:
 *   bin/cake reset_admin
 */
class ResetAdminCommand extends Command
{
    public const ADMIN_EMAIL = 'admin@ai-agents.local';
    public const DEFAULT_USERNAME = 'admin';
    public const DEFAULT_PASSWORD = 'Admin123!';

    /**
     * @inheritDoc
     */
    public static function defaultName(): string
    {
        return 'reset_admin';
    }

    /**
     * @inheritDoc
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser->setDescription(
            'Resets the administrator account (admin@ai-agents.local) to default credentials.'
            . ' Use only in development or when locked out.'
        );

        return $parser;
    }

    /**
     * @inheritDoc
     */
    public function execute(Arguments $args, ConsoleIo $io): int
    {
        $users = TableRegistry::getTableLocator()->get('Users');

        /** @var \App\Model\Entity\User|null $admin */
        $admin = $users->find()
            ->where(['email' => self::ADMIN_EMAIL])
            ->first();

        if ($admin === null) {
            $io->error(sprintf(
                'Admin user with email "%s" not found. Run "bin/cake migrations seed --seed AdminUserSeed" first.',
                self::ADMIN_EMAIL
            ));

            return self::CODE_ERROR;
        }

        $admin->username = self::DEFAULT_USERNAME;
        $admin->password = self::DEFAULT_PASSWORD; // triggers _setPassword bcrypt mutator
        $admin->is_active = true;
        $admin->is_approved = true;
        $admin->approval_state = 'approved';
        $admin->mfa_enabled = false;
        $admin->mfa_secret = null;

        if (!$users->save($admin)) {
            $io->error('Failed to save admin user. Validation errors:');
            foreach ($admin->getErrors() as $field => $errors) {
                foreach ($errors as $message) {
                    $io->error(sprintf('  %s: %s', $field, $message));
                }
            }

            return self::CODE_ERROR;
        }

        $io->success('Admin account reset successfully.');
        $io->out('');
        $io->out(sprintf('  Email:    %s', self::ADMIN_EMAIL));
        $io->out(sprintf('  Username: %s', self::DEFAULT_USERNAME));
        $io->out(sprintf('  Password: %s', self::DEFAULT_PASSWORD));
        $io->out('');
        $io->warning('Change the password after logging in!');

        return self::CODE_SUCCESS;
    }
}
