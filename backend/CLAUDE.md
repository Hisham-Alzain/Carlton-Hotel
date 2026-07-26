# Carlton Backend — agent instructions

## MANDATORY: the `tupcode-laravel-backend` skill

Before writing, reviewing, planning, or refactoring **any** code under this directory,
invoke the skill `tupcode-laravel-backend` (listed as `backend:tupcode-laravel-backend`
when the session root is the monorepo rather than this directory). If it is not in the
available-skills list, read
`.claude/skills/tupcode-laravel-backend/SKILL.md` directly instead — the rules apply
either way.

This is not optional and applies to every agent and subagent that touches this
directory, including one-line changes. It covers:

- Layered architecture — `Base*` classes, services, actions, filters, and what each
  layer may and may not know about.
- The response envelope (`sendResponse()`, global handler) and domain exceptions
  keyed by stable `error_code`.
- AR/EN localization rules (`__('custom.key')` present in both `lang/en` and `lang/ar`).
- Database design and usage rules — indexes, transactions, eager loading, money as
  `DECIMAL`, UUIDs for public routes, additive migrations.
- Security, queueing, testing, and the pre-merge PR checklist.

Full reference: `.claude/skills/tupcode-laravel-backend/references/developer-guide.md`.
Read it before building a complete feature; the SKILL.md rules are the contract.

### When to load it

Load the skill *before* opening the target file — do not skip because a change
"looks trivial". A single endpoint, migration, resource, or bug fix must still
follow these conventions.

### Before declaring a change done

Run the PR checklist in guide §17. Treat it as a gate, not a suggestion.

## Related project skills

`laravel-conventions`, `module-slice`, `test-discipline`, and `naive-reviewer` remain
available. Where they disagree with `tupcode-laravel-backend`, the
`tupcode-laravel-backend` skill wins.

## Git

Commit after each phase of work. Do not push.
