# CellCraze — Implementation Protocol (one task at a time)

How every future Claude Code session must implement, test, review and accept **exactly one** task.
Never auto-continue to the next task. Never commit/push/deploy or mutate a production DB unless
separately authorized.

## Reusable prompt template (fill in `<TASK-ID>`)

```
You are implementing exactly ONE task for CellCraze: <TASK-ID>.

Read first (do not skip):
- docs/CELLCRAZE-MASTER-IMPLEMENTATION-ROADMAP.md
- docs/CELLCRAZE-TASK-SPECS.md  → the <TASK-ID> spec
- docs/CELLCRAZE-DECISIONS-AND-OPEN-QUESTIONS.md  → any blocking decision
- docs/CELLCRAZE-IMPLEMENTATION-STATUS.md  → current status + prior task reports
- docs/FEATURE-TRACEABILITY.md

Rules:
1. Run `git status` and inspect relevant diffs. PRESERVE all unrelated/uncommitted work —
   never reset, stash, discard, revert, or force.
2. Confirm the environment and that prerequisites for <TASK-ID> are met. If the DB is unhealthy
   (Aria/mysql.plugin issue), do read-only diagnosis only — never force-recover.
3. Inspect the ACTUAL code before changing it. State intended changes + risks before substantial
   edits.
4. Implement ONLY <TASK-ID>'s scope. Reuse shared services (IMEI_DB claim/restore, IMEI_Orders,
   Audit::log, Stock Guard, COGS, Channel) — do not reimplement them.
5. For any change to inventory/payments/IMEI/refunds/accounting: add explicit duplicate-request,
   partial-failure, concurrency, and reconciliation handling + tests.
6. Run focused tests, `php -l` on every changed PHP file, and the listed regression checks.
7. Review the diff for unrelated changes and security issues (nonces, caps, escaping, prepared SQL).
8. Update docs/CELLCRAZE-IMPLEMENTATION-STATUS.md with evidence; report PASSED / FAILED / BLOCKED /
   NOT-RUN tests separately. Never claim a test passed unless it was executed.
9. STOP. Do not start the next task. Do not commit/push/deploy unless explicitly authorized in
   this session.
10. If a critical requirement is ambiguous or a decision (D1–D10) is unresolved, stop and ask.
```

## Acceptance gate (per task)
A task moves to **Accepted** only when: scope met; acceptance checkboxes all true; regression set
green (listed, executed); security reviewed; status tracker updated with evidence; reviewer signs
off. Only then may the next task start.

## Security checklist (every state-changing action)
- Capability check server-side (not menu hiding) + nonce on admin forms.
- Input validated/sanitized; output escaped; `$wpdb->prepare` for all SQL.
- REST routes have real `permission_callback`; webhooks/callbacks verified + idempotent.
- No secrets/PII in logs; private files stay in the gated store.
- Idempotency key on anything that moves stock/money/IMEI.

## Testing strategy (adapted to this repo)
- **No assumption of PHPUnit.** Prefer: `php -l` syntax checks; WP-CLI (`wp eval-file`) scripts for
  logic/integration checks against a dev DB; documented manual HTTP/browser scenarios (as used for
  CC-03/04/06/09/13). If/when PHPUnit + wp-phpunit is added, migrate logic tests there.
- Record each run's result verbatim; separate PASSED/FAILED/BLOCKED/NOT-RUN.

## Git / deployment discipline
- Default: **do not commit or push** during implementation unless the user authorizes it in that
  session. Planning docs were committed to `main` by explicit user request; code is not.
- Never mutate a production database. Never deploy from a task session.

## Release readiness checklist (QA-01)
- [ ] Regression suite green (CC-03/04/06/09/13, ADM-05/08/09/10, T-101 + new).
- [ ] Permission tests pass for each role (cashier/warehouse/order-processing/manager/admin).
- [ ] Duplicate-submission + concurrency tests pass for inventory/payment/IMEI/refund paths.
- [ ] HPOS compatibility verified. [ ] Storefront responsive + a11y pass.
- [ ] Payment test-mode verified (when gateway added). [ ] POS print verified (when POS added).
- [ ] Inventory + accounting reconciliation balances. [ ] Backup/restore verified.
- [ ] Staging acceptance signed; production release notes prepared.
