<?php
declare(strict_types=1);

namespace App\Channels\Slack\Service;

use App\Channels\Slack\SlackClientInterface;
use App\Channels\Slack\SlackException;
use App\Messaging\Dto\InboundEnvelope;
use App\Model\Entity\Agent;
use App\Model\Entity\User;
use App\Model\Entity\UserChannelIdentity;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

/**
 * Identity resolution for inbound Slack messages.
 *
 * Slack already authenticated the sender — there is no OTP step. The job
 * here is to map the Slack user_id to a platform User row, creating one as
 * a guest if no mapping exists yet. The mapping is persisted in
 * user_channel_identities so subsequent inbound from the same user is a
 * single row lookup.
 *
 * On first contact:
 *   1. Look up existing user_channel_identity for (slack, slack_user_id)
 *   2. If miss, fetch the Slack user's email via users.info and try to
 *      match an existing platform User by email; if matched, persist the
 *      identity row and return that user
 *   3. Otherwise, create a new User on the slack_guest role with
 *      approval_state=pending, persist the identity row, return the user
 *
 * In all branches the inbound job continues normal processing (the current
 * envelope IS a real chat message, not a verification artifact). The
 * approval gate in ProcessInboundMessageJob then decides whether to route
 * the message to the agent handler.
 */
class SlackOnboardingService
{
    public function __construct(
        private readonly SlackClientInterface $client,
        private readonly SlackConfigService $configService,
    ) {
    }

    public function handle(InboundEnvelope $envelope, ?Agent $agent): ?User
    {
        if ($agent === null) {
            return null;
        }
        $config = $this->configService->findConfigByAgentId($agent->id);
        if ($config === null) {
            return null;
        }

        $identities = TableRegistry::getTableLocator()->get('UserChannelIdentities');
        $existing = $identities->find('byExternal', channel: 'slack', externalId: $envelope->externalIdentifier)->first();
        if ($existing !== null) {
            return $existing->user ?? null;
        }

        $profile = $this->fetchProfile($config->botToken, $envelope->externalIdentifier);

        $users = TableRegistry::getTableLocator()->get('Users');
        $user = null;
        if (!empty($profile['email'])) {
            /** @var User|null $user */
            $user = $users->find()->where(['Users.email' => $profile['email']])->first();
        }

        if ($user === null) {
            $user = $this->createGuest($profile, $envelope->externalIdentifier);
            if ($user === null) {
                return null;
            }
        }

        $this->persistIdentity($user, $envelope, $profile);

        return $user;
    }

    /** @return array{id: string, name: string, real_name: ?string, email: ?string, team_id: ?string} */
    private function fetchProfile(string $botToken, string $slackUserId): array
    {
        try {
            return $this->client->getUserInfo($botToken, $slackUserId);
        } catch (SlackException) {
            // users.info can fail on workspaces that haven't granted users:read.email.
            // Fall back to a minimal stub so we can still create a guest user.
            return [
                'id' => $slackUserId,
                'name' => $slackUserId,
                'real_name' => null,
                'email' => null,
                'team_id' => null,
            ];
        }
    }

    /** @param array<string, mixed> $profile */
    private function createGuest(array $profile, string $slackUserId): ?User
    {
        $roles = TableRegistry::getTableLocator()->get('Roles');
        $role = $roles->find()->where(['slug' => 'slack_guest'])->first();
        if ($role === null) {
            return null;
        }

        $email = (string)($profile['email'] ?? "slack-{$slackUserId}@guests.local");
        $username = 'slack_' . strtolower($slackUserId);
        // Slack ids are unique within a workspace but emails may collide if the
        // user signed up with the same email on another platform path; fall
        // back to a uniqueness-safe variant.
        $users = TableRegistry::getTableLocator()->get('Users');
        $emailTaken = $users->find()->where(['Users.email' => $email])->count() > 0;
        if ($emailTaken) {
            $email = "slack-{$slackUserId}@guests.local";
        }

        $entity = $users->newEntity([
            'email' => $email,
            'username' => $username,
            'password' => bin2hex(random_bytes(16)),
            'first_name' => 'Slack',
            'last_name' => (string)($profile['real_name'] ?? $profile['name'] ?? $slackUserId),
            'role_id' => $role->id,
            'is_active' => true,
            'is_approved' => false,
            'approval_state' => User::APPROVAL_PENDING,
        ]);
        if (!$users->save($entity)) {
            return null;
        }
        /** @var User $entity */
        return $entity;
    }

    /** @param array<string, mixed> $profile */
    private function persistIdentity(User $user, InboundEnvelope $envelope, array $profile): void
    {
        $identities = TableRegistry::getTableLocator()->get('UserChannelIdentities');
        $teamId = $profile['team_id'] ?? null;
        // Slack passes the workspace as the top-level "team_id" on event payloads;
        // fall back to that when users.info didn't include it.
        if ($teamId === null && isset($envelope->rawPayload['team'])) {
            $teamId = (string)$envelope->rawPayload['team'];
        }

        $entity = $identities->newEntity([
            'user_id' => $user->id,
            'channel' => 'slack',
            'external_id' => $envelope->externalIdentifier,
            'external_team_id' => $teamId,
            'display_name' => $profile['real_name'] ?? $profile['name'] ?? null,
            'email' => $profile['email'] ?? null,
            'verified_at' => new DateTime(),
        ]);
        $identities->save($entity);
    }
}
