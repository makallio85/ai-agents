# Agent rules for this repo

> **Full development workflow guide**:
> https://github.com/makallio85/infra-docs/blob/main/agent-workflow.md
>
> Supervisor architecture (forward-looking, not built yet):
> https://github.com/makallio85/infra-docs/blob/main/agent-supervisor-architecture.md

## Top rules — read before opening a PR

1. **Never merge a PR.** Humans merge. When done, comment `ready for review` and stop.
2. **Never push directly to `dev` or `main`.** Always work in a feature branch (`issue-<N>-<slug>`) with a PR.
3. **Stay in your repo + your branch.** No reading or writing outside this repo, no pushing to other people's branches, no `gh` calls against repos you weren't told to work on.
4. **Don't touch template-managed files** (`docker/`, `docker-compose.yml`, `Dockerfile`, `.github/workflows/`, this rule file). If you must, STOP and ask.
5. **Use `${SERVICE_NAME_*}` for cross-service hostnames** in compose env. Hardcoded `db` / `redis` breaks per-PR isolation.
6. **No silent bypasses.** Don't add `@ts-ignore`, swallow exceptions, lower coverage thresholds, or remove failing assertions to make CI green.
7. **No secret leakage.** Never `echo`/`cat`/`printenv`/comment any env var matching `PASSWORD|SALT|KEY|TOKEN|SECRET`.
8. **Hit a hard-stop condition? STOP and comment.** Don't retry, don't escalate, don't improvise. Full list in the [canonical doc §5](https://github.com/makallio85/infra-docs/blob/main/agent-workflow.md#5-soft-sandbox-no-technical-isolation--rules-are-the-boundary).

## Stack at a glance

CakePHP 5 + Vue 3 + Vite + SCSS + MariaDB + Redis. Single-container (php-fpm + nginx + supervisord). See the canonical doc for full details, env vars, and failure diagnosis.

## Repo-specific notes

<!-- Each repo can add overrides or specifics below. By default: none. -->
