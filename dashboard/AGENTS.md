# Repository Instructions

## Project Context

Carlton is a mock-first hotel staff operations dashboard. Before starting any task, read these files in order:

1. `CLAUDE.md` — full architecture reference, tech stack, Carlton brand tokens, CartX comparison, coding rules
2. `CARLTON_DASHBOARD_TRACKER.md` — current milestone status and task backlog (authoritative source of what is built vs. todo)
3. `CARLTON_DASHBOARD_FOUNDATION.md` — product spec, API envelope contracts, page inventory, brand guidelines
4. `CARLTON_DASHBOARD_EXECUTION_PLAN.md` — strategy, milestone breakdown, working rules

CartX reference codebase (structural patterns, not code to copy): `C:\tupcode\cartx\cartx-admin\`

Key rules for all agents:
- No TypeScript — plain JS + JSX only
- No Tailwind or CSS framework — use Carlton CSS tokens in `src/styles/tokens.css` and patterns in `src/styles/global.css`
- All new mock data handlers go in `src/mocks/data/` and must be wired through `src/mocks/mockClient.js`
- All API calls go through `src/services/` → `src/store/` — never fetch directly from components
- Permission checks use `src/utils/permissions.js` — never gate on role strings, always on permission constants
- UI/UX quality: data-dense, operational, luxury hotel aesthetic — not a marketing page

<!-- codex-swarm-desktop:start -->
## Codex Swarm Desktop

- This repo is bootstrapped for the local swarm workflow defined in `AGENTS.codex-swarm.md` and `.codex-swarm/agentctl.md`.
- Use `python .codex-swarm/agentctl.py` for task operations and config changes; do not hand-edit `.codex-swarm/tasks.json`.
- Default to `workflow_mode=direct`; switch to `branch_pr` only when strict per-task branches/worktrees are requested.
- For development work, prefer the flow `ORCHESTRATOR -> PLANNER -> CODER -> TESTER -> REVIEWER -> INTEGRATOR`.
<!-- codex-swarm-desktop:end -->

