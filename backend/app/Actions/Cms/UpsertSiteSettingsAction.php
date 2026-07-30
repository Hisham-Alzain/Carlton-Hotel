<?php

namespace App\Actions\Cms;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-upsert website settings in one atomic write.
 *
 * WHY THIS IS AN ACTION AND NOT A SERVICE METHOD (guide §10):
 *
 * - It is a **verb**, not a resource. The CMS settings screen has no notion of
 *   creating or deleting one setting; it submits the form it was given and the
 *   whole form either lands or does not. `BaseService::update()` cannot express
 *   that — it takes one model.
 * - The write is **multi-row and all-or-nothing**. One transaction wrapping N
 *   `updateOrCreate` calls is the entire point of the class, and the shape of
 *   the operation (a loop over a payload, keyed by a composite identity) has
 *   nothing in common with the declarative CRUD `BaseService` provides.
 *
 * The two §10 criteria that do NOT apply: it spans a single domain, and
 * `SiteSettingService` is nowhere near 300 lines. One criterion is enough.
 *
 * The repo has no `BaseAction` class — every existing action under `app/Actions`
 * is a plain class exposing `handle()` and calling `DB::transaction` directly
 * (see `Actions\Review\SetReviewPublishedAction`). This follows the codebase
 * rather than the guide's `extends BaseAction` / `execute()` sketch, because
 * inventing a base class for one caller would leave two conventions in the tree.
 */
class UpsertSiteSettingsAction
{
    /**
     * @param  list<array{group: string, key: string, value: mixed, type: string, is_active?: bool}>  $settings
     * @return array{data: null, code: int}
     */
    public function handle(array $settings): array
    {
        // ATOMIC BY REQUIREMENT, not by habit. A settings form is one editorial
        // act: if row 7 fails, rows 1–6 must not be live, because a footer whose
        // tagline updated and whose copyright did not is a worse state than the
        // one before the save — and the editor has no way to tell which half
        // landed. Removing this transaction turns a failed save into a silent
        // partial publish; `SiteSettingTest::test_bulk_upsert_is_atomic` is the
        // guard.
        DB::transaction(function () use ($settings): void {
            foreach ($settings as $row) {
                $attributes = [
                    'value' => $row['value'],
                    'type'  => $row['type'],
                ];

                // Only touch `is_active` when the caller said something about
                // it. Defaulting to true here would silently re-publish a
                // setting an editor had deliberately hidden, on any save of the
                // rest of the form. On insert the column default covers it.
                if (array_key_exists('is_active', $row)) {
                    $attributes['is_active'] = $row['is_active'];
                }

                SiteSetting::updateOrCreate(
                    ['group' => $row['group'], 'key' => $row['key']],
                    $attributes,
                );
            }
        });

        // No data: the caller re-reads the whole grouped set, because a partial
        // echo of just the written rows is the shape most likely to be mistaken
        // for "these are all the settings".
        return ['data' => null, 'code' => 200];
    }
}
