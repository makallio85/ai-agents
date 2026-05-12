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
 * the admin password has been lost. Finds the first user with the
 * 'administrator' role slug — regardless of their email address.
 *
 * Resets username, password, and ensures the account is active and approved.
 * The bcrypt hash is applied automatically via the User entity mutator.
 *
 * Usage:
 *   bin/cake reset_admin
 */
class ResetAdminCommand extends Command
{
    public const ADMIN_ROLE_SLUG = 'administrator';
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
            'Resets the first administrator account to default credentials.'
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
            ->contain(['Roles'])
            ->matching('Roles', function ($q) {
                return $q->where(['Roles.slug' => self::ADMIN_ROLE_SLUG]);
            })
            ->orderBy(['Users.id' => 'ASC'])
            ->first();

        if ($admin === null) {
            $io->error(sprintf(
                'No user with role "%s" found. Run "bin/cake seeds run InitialDataSeed" and "bin/cake seeds run AdminUserSeed" first.',
                self::ADMIN_ROLE_SLUG
            ));

            return self::CODE_ERROR;
        }

        $io->out(sprintf('Found admin user: %s (email: %s)', $admin->username, $admin->email));

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
        $io->out(sprintf('  Email:    %s', $admin->email));
        $io->out(sprintf('  Username: %s', self::DEFAULT_USERNAME));
        $io->out(sprintf('  Password: %s', self::DEFAULT_PASSWORD));
        $io->out('');
        $io->warning('Change the password after logging in!');

        return self::CODE_SUCCESS;
    }
}
