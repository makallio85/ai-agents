<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * Fixture for the mfa_tokens table.
 *
 * No pre-seeded records — tests that need tokens create them during the test.
 * The fixture exists so CakePHP's TruncateStrategy can reset the table
 * between test runs without throwing "fixture not found" errors.
 */
class MfaTokensFixture extends TestFixture
{
    public array $records = [];
}
