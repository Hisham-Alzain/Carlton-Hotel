# Phase 10 Discussion Log (plan-phase gates, 2026-10-04)

Standing rule: full auto, no user prompts mid-milestone; decisions go to the Fable consultant.

| Gate | Decision | Decided by |
|------|----------|------------|
| Research vs skip | Research first (workflow recommendation; CONTEXT open items needed code research) | Orchestrator, no user prompt |
| Researcher run | First run died on an API/DNS error after writing a complete 10-RESEARCH.md (ends with Metadata); accepted as RESEARCH COMPLETE | Orchestrator |
| Research open questions Q1-Q25 (11 HIGH) | Accept 21 defaults, override Q4 (three capabilities), Q9 (not on cancelled), Q16 (`clawback` type), Q19 (`issued` excludes refunds); Q3 and Q6 tightened | Fable consultant (single model; no ai-council convened; dissent on Q2: LIFO recovers more value, rejected for one ordering rule) |
| Phase 9 dependency | Soft ordering dependency only | Fable consultant, matches research |
| Requirements | LOY-01..LOY-22 added to REQUIREMENTS.md; ROADMAP Phase 10 goal and success criteria written | Orchestrator from consultant output |
| ROADMAP `Mode: mvp` | Not adopted (consultant suggested it, but it changes plan shape to vertical slices; Phase 10 stays standard) | Orchestrator |

Full rulings are in `10-CONTEXT.md` section "Resolved planning rulings".

## Later gates (same run)
| Gate | Decision | Decided by |
|------|----------|------------|
| UI-SPEC gate (frontend=true false positive from dashboard/ and mobile/ dirs) | Skipped; phase is backend API only | Orchestrator |
| Codebase-drift advisory | Ignored (non-blocking) | Orchestrator |
| Plan checker | VERIFICATION PASSED on first iteration, 15 plans, no blockers/warnings | gsd-plan-checker (haiku) |
| Requirements / decision coverage | LOY-01..22 all in plans; decision gate skipped (CONTEXT has no `<decisions>` block) | Orchestrator |
| Phase 9 | Committed (82169a5) before plans finished; soft-dependency note kept | n/a |

## Gap-closure run (`/gsd-plan-phase 10 --gaps`, 2026-10-05)
Trigger: `10-VERIFICATION.md` status `gaps_found` (6/7): ROADMAP obligation "forfeit loyalty balance when `DeleteGuestAccountAction` deletes the account" is unmet. Standing rule unchanged: full auto, no user prompts, decisions to the Fable consultant.

| Gate | Decision | Decided by |
|------|----------|------------|
| Closed-phase gate | Not gated: `phase_status` is `Executed` (VERIFICATION is `gaps_found`) | Workflow, deterministic |
| Research | Skipped (`--gaps`); `10-RESEARCH.md` and `10-PATTERNS.md` reused, no new research | Workflow flag |
| Gap requirement | Added LOY-23 to REQUIREMENTS.md (plus the missing LOY-01..22 traceability rows) and ROADMAP `Requirements: LOY-01 .. LOY-23`, so the unmet "Phase 10 must add" obligation is a traceable requirement the gap plan can cite | Orchestrator |
| Existing plans (15, all executed) | Add gap-closure plans after 10-15, never replan executed plans | Orchestrator, deterministic for `--gaps` |
| UI-SPEC gate | Skipped: same `frontend=true` false positive as the first run (dashboard/ and mobile/ dirs); the gap is backend only | Orchestrator, precedent above |
| Codebase-drift advisory | Ignored, non-blocking (1027 elements, mostly `.planning/` files; map is old) | Orchestrator |
| Spec-less probe fallback | No SPEC.md, so it ran for LOY-23 only (the requirement this run plans): 3 applicable edges (adjacency, empty, ordering), passed to the planner as unresolved to author into `must_haves` | Orchestrator |
| Pattern mapper | Skipped, `10-PATTERNS.md` exists | Workflow |
