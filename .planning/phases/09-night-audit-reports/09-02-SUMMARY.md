---
phase: 09-night-audit-reports
plan: 02
status: complete
---

# 09-02 Summary — exact SQL money aggregation

## Built
- `app/Support/MoneyAggregate.php` (final, static): `centsExpression($column, ?$driver)` (sqlite `CAST(ROUND(col*100) AS INTEGER)`, mysql/mariadb `ROUND(col*100)`, otherwise `LogicException`), `positiveCentsExpression`, `negativeCentsExpression`, `fromCents(int|string|null)` → 2dp string via bcmath (null → `"0.00"`, `-0.00` folded to `"0.00"`, MySQL `"8600.00"`-style strings normalised). No float anywhere.

## Tests
- `tests/Unit/Support/MoneyAggregateTest.php` (5): formatting, MySQL decimal strings, per-driver SQL, conditional variants, pgsql → LogicException.
- `tests/Feature/Reports/MoneyAggregateSqlTest.php` (7, real SQLite): empty, 10 × 0.10, 0.29 + 0.57, mixed signs, negatives only, 50 × 99999999.99, charges + credits = net; raw SQL values asserted not float.
- RED confirmed before the class existed; GREEN with `--filter='MoneyAggregateTest|MoneyAggregateSqlTest|Folio'` (181 passed).
- `FolioLedger.php` and `Folio.php` unchanged.

## Deviations
- None. MySQL exactness remains a manual check (09-VALIDATION).
