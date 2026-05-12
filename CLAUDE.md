# AI-Agents Platform — CLAUDE.md

Project-specific architectural rules and conventions for Claude Code.

---

## Configuration

- `config/.env` — environment variables (API keys, SMTP, debug flag)
- `config/app_local.php` — local database config
- `config/app.php` — main CakePHP config
- `config/routes.php` — URL routing
- `config/Migrations/` — Phinx database migrations
- `phinx.yml` — migration tool config
- `phpstan.neon` — static analysis config

## Development workflow (temporary — until further notice)

- ALWAYS commit directly to `master` — no feature or bugfix branches during this phase
- ALWAYS push to remote after every commit
- ALWAYS make sure PHPUnit and PHPStan pass before committing
- Branching rules in the global CLAUDE.md are suspended for this project until further notice

## Testing

Tests live in `tests/TestCase/`. Fixtures are in `tests/Fixture/`. The test database is configured separately from the development database (see `phpunit.xml.dist`).

PHPUnit configuration is in `phpunit.xml.dist`. The bootstrap is `tests/bootstrap.php`.

- ALWAYS make sure full PHPUnit testsuite passes before committing changes

## Bugfix workflow — test-first

When fixing a bug, ALWAYS follow this order:

1. Write a failing test that reproduces the bug first
2. Verify the test fails (red) — confirming the bug is caught
3. Fix the bug
4. Verify the test passes (green) — the bugfix is complete

A bugfix is not done until the covering test passes. Never fix the bug before writing the test.

## Code quality
- ALWAYS make sure PHPStan passes before committing changes

## Testing rules — isolation is mandatory

Tests MUST be fully isolated from external systems. This applies everywhere, without exception:

- **No network requests** — no HTTP calls to any external or internal service
- **No emails** — no SMTP, no email API calls
- **No external API calls** — no GitHub API, Twilio, OpenAI, or any other third-party service
- **No side effects** — tests must not write to shared queues, send notifications, or trigger background jobs

Use static fixtures (`tests/Fixture/`) for parser and DOM-parsing tests. If a class makes HTTP calls, test only its parsing methods against fixture files — never call the network-facing methods in tests. If a class must be isolated from a network dependency, introduce an interface or inject the HTTP client as a constructor parameter so it can be replaced with a test double.

## Shared Validation Classes

Reusable format and business-rule validators live in `src/Validation/`.

All validators extend `App\Validation\Validation` (abstract base in `src/Validation/Validation.php`).
Implement the single abstract method `validate()` and set `$this->result` to `true` or `false`.

```php
class SomeValidation extends Validation
{
    protected function validate(): void
    {
        $this->result = (bool)preg_match('/pattern/', $this->value);
    }
}

// Usage:
$valid = (new SomeValidation())->setValue($rawValue)->validates();
```

Validators should not normalise their input.
Use in Services for business-logic checks. Do not duplicate with CakePHP Table validation rules.

## Code block documentation

Every non-trivial class, method, service, job, integration, and architectural pattern MUST have a PHPDoc block that explains:

- **What** it does (one-line summary)
- **Why** it exists — the business or technical reason, not just a restatement of the name
- **How** it fits into the broader system — what calls it, what it depends on, what it produces
- Any non-obvious constraints, edge cases, or gotchas (e.g. "must be idempotent", "rate-limited by GitHub")

### Rules

- ALWAYS add or update the doc block when creating or modifying a class or method
- NEVER leave doc blocks stale — if behaviour changes, the doc block changes in the same commit
- Doc blocks are not optional. They exist so that future Claude sessions and developers can understand decisions without reading the full context again
- Prefer explaining **intent and reason** over restating types that are already in the signature

### Example — good doc block

```php
/**
 * Parses raw conversation text and extracts structured issue blocks.
 *
 * Issue blocks are delimited by === ISSUE START === / === ISSUE END ===.
 * Used by ParseIssueJob to split a single pasted conversation into
 * individual ParsedIssueDto objects before dispatching GitHub creation jobs.
 *
 * The `m` flag on body extraction regex is critical: without it `^fieldname`
 * only matches at the very start of the string, not at the start of each line.
 */
class IssueParserService { ... }
```

### Example — bad doc block

```php
/**
 * Parses issues.
 */
class IssueParserService { ... }
```

## Best practices, PSR coding standards, conventions
When coding:
- ALWAYS follow CakePHP conventions for naming

## Type tables in database
When creating new type tables:
- ALWAYS use following columns names: id (int 10), value (varchar 255), created (datetime), modified (datetime)

## Join tables (belongsToMany)
When creating join tables for many-to-many relationships YOU MUST name tables by CakePHP convention:
- ALWAYS name them by combining both table names in alphabetical order separated by `_`
- Example: `cars` + `tires` → `cars_tires`
- ALWAYS specify the join table explicitly in both `belongsToMany` associations using the `joinTable` option

## Method names
- ALWAYS when creating frontend or backend code, use CRUD pattern as first choice of option (C=create/add, R=read/view, U=update, D=delete). As an example, routes for "Cars" would be: cars (gets all), cars/create (creates), cars/read/1 (reads single record), cars/update/1 (updates single record), cars/delete/1 (deletes single record)
- ALWAYS when creating any code, use "findBy...()" pattern, when method is purely just finding data and CRUD cannot be applied.
- ONLY when CRUD and "findBy...()" patterns cannot be applied for reasonable result, create method and use descriptive naming for method and parameters.

## Routes
When creating new routes:
- ALWAYS when possible, apply same rules as described in section ## Method names
- ALWAYS make sure to not to create unnecessary manual routes when CakePHP's default routing by convention would already provide automatic and working route
- ALWAYS when manual route is absolutely needed, provide reason for it and ask for permission to create it
- ALWAYS avoid nested controllers and actions as they overcomplicate code
- ALWAYS provide direct context entity id's as action parameters (/api/v1/agents/view/1)
- ALWAYS make sure that html content is served from root urls (/agents/view/1) and JSON API content is served through api urls (/api/v1/agents/view/1)

## AI Agents
- ALWAYS implement new agents as plugins

## Architecture rules

- NEVER place business logic into Controllers
- ALWAYS use Services, Actions, Managers or Queue Jobs for business logic
- Controllers must only:
  - validate request shape
  - call service layer
  - format response

- ALWAYS separate:
  - domain logic
  - infrastructure logic
  - API integrations
  - queue execution logic

- ALWAYS inject dependencies through constructors where possible
- NEVER instantiate external clients directly inside business logic

## Queue & async processing

- ALL long-running operations MUST run through queue jobs
- NEVER execute external API calls directly during HTTP request lifecycle unless explicitly required
- Queue jobs MUST:
  - be retryable
  - be idempotent
  - produce logs
  - handle partial failures gracefully

- Queue jobs MUST NOT contain duplicated business logic
- Shared logic belongs into Services

## AI Agent architecture

- EVERY agent MUST be implemented as isolated plugin
- EVERY agent MUST expose:
  - configuration
  - execution handler
  - context management
  - logging support
  - queue integration

- Agent logic MUST NOT depend directly on UI
- Agent execution MUST be callable:
  - manually
  - through queue
  - through API

- Agents MUST support future multi-agent orchestration

## Logging

- ALL agent executions MUST be logged
- ALL external API requests MUST be logged
- ALL failed queue jobs MUST be logged
- ALL retries MUST be logged

Logs MUST include:
- execution id
- agent id
- user id if available
- correlation id
- execution duration
- result state
- error message if failed

## API standards

- ALWAYS version API routes (/api/v1/)
- ALWAYS return structured JSON responses
- NEVER return raw exceptions to client
- ALWAYS use proper HTTP status codes

JSON responses MUST follow consistent structure:
```json
{
  "success": true,
  "data": {},
  "errors": [],
  "meta": {}
}
```

## Database migrations

- NEVER modify old migrations after committed
- ALWAYS create new migration for schema changes
- ALL foreign keys MUST be indexed
- ALL tables MUST include:
  - created
  - modified

unless technically impossible

## Frontend architecture

- Use Vue 3 Composition API
- Use Pinia for shared state
- API calls MUST be centralized
- NEVER place business logic into Vue components
- Components MUST remain presentation-focused

Preferred structure:
```
webroot/js/vue/
├── api.js
├── pages/
├── components/
├── stores/
├── services/
└── composables/
```

## Security

- NEVER store secrets in source code
- ALWAYS use environment variables
- Sensitive values MUST be encrypted at rest
- MFA secrets MUST be encrypted
- GitHub tokens MUST be encrypted
- Twilio credentials MUST be encrypted

- ALWAYS validate:
  - permissions
  - ownership
  - access scope

on backend side

## OpenAI / LLM integrations

- NEVER trust LLM output directly
- ALWAYS validate parsed structures
- ALWAYS sanitize markdown/html output
- LLM responses MUST be logged
- Prompt versions MUST be versioned

- ALL prompts MUST be stored in dedicated structures
- Prompts MUST support future versioning and rollback

## Project philosophy

This system is intended to become enterprise-grade AI orchestration platform.

Architecture decisions MUST prioritize:
- maintainability
- modularity
- observability
- scalability
- testability

over short-term implementation speed.
