<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The token this process suffixes every fake-disk root with. Minted once by
     * `fakeDiskToken()` and memoised here.
     */
    private static ?string $fakeDiskToken = null;

    /**
     * The separator Laravel puts between a fake disk's name and its run token
     * (`Storage::fake()` builds `"{$root}_test_{$token}"`).
     */
    private const TOKEN_MARKER = '_test_';

    /**
     * Absolute path to the directory Laravel roots every `Storage::fake()` disk in.
     *
     * Resolved from __DIR__ rather than storage_path() so it is usable before the
     * application container exists.
     *
     * Deliberately not named `testingDisksPath()`: PHPUnit still collects any
     * public method whose name begins with "test" as a test case, which would
     * add a phantom (and risky, assertion-free) test to every class in the suite.
     */
    public static function fakeDisksRoot(): string
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR
            .'storage'.DIRECTORY_SEPARATOR
            .'framework'.DIRECTORY_SEPARATOR
            .'testing'.DIRECTORY_SEPARATOR
            .'disks';
    }

    /**
     * The token every `Storage::fake()` root in this process is suffixed with.
     *
     * `Storage::fake('public')` roots the disk at
     * `…/testing/disks/public_test_{ParallelTesting::token()}` and **cleans that
     * directory** on the way in. With no token the root is `…/disks/public` —
     * one directory shared by every process on the machine — so two plain
     * `php artisan test` runs (two terminals, two agents; no `--parallel`
     * involved) delete each other's uploads mid-test. That is what produced
     * repeated false failures in `MediaLibraryTest`, `GalleryTest` and
     * `MediaCleanupTest` which passed the moment they were run on their own.
     *
     * `--parallel` already exports one `TEST_TOKEN` per worker; that value is
     * reused rather than replaced, so a parallel run keeps the roots Laravel
     * gave it. Otherwise one is minted here — pid **plus** random bytes, because
     * a pid on its own repeats after a wrap or a reboot — and published on
     * `$_SERVER`/`$_ENV`, which is the same channel `--parallel` uses and
     * therefore the same code path inside `ParallelTesting::token()` rather than
     * a second mechanism to keep working.
     *
     * Publishing `TEST_TOKEN` does not make the framework think it is running in
     * parallel: `ParallelTesting::inParallel()` reads
     * `LARAVEL_PARALLEL_TESTING_IN_PARALLEL`, and the only caller of `token()`
     * besides `Storage::fake()` is `Testing\Concerns\TestDatabases`, which runs
     * from the parallel runner and is gated on `inParallel()`. The database needs
     * no such treatment anyway — `phpunit.xml` pins sqlite `:memory:`, which is
     * already private to a process.
     */
    public static function fakeDiskToken(): string
    {
        if (self::$fakeDiskToken !== null) {
            return self::$fakeDiskToken;
        }

        $token = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null;

        if (! is_string($token) || $token === '') {
            $token = getmypid().'x'.bin2hex(random_bytes(4));

            $_SERVER['TEST_TOKEN'] = $token;
            $_ENV['TEST_TOKEN']    = $token;

            // A run that mints its own token owns its own directories, so it
            // takes them with it on the way out — otherwise `storage/` grows by
            // one root per run forever. Not registered when `--parallel` handed
            // us the token: those roots belong to the runner's lifecycle.
            register_shutdown_function(static function (): void {
                self::purgeTestingDisks();
            });
        }

        return self::$fakeDiskToken = $token;
    }

    /**
     * Delete this run's fake-disk artifacts, and any untokenised residue.
     *
     * `Storage::fake('public')` only cleans the one disk it is given, so files
     * written to any other disk — or written by a test that crashed before its
     * assertions — survive on disk and are still there when the next test
     * starts. That residue makes storage-sensitive tests pass or fail depending
     * on what ran before them: a suite that is 432/438 on the first run and
     * 438/438 on every run after the directory is deleted by hand. A suite that
     * fails randomly trains people to ignore failures, so this still runs before
     * every test.
     *
     * What it no longer does is delete another run's disks. It used to remove
     * `…/testing/disks` whole and guard only on `TEST_TOKEN`, i.e. only under
     * `--parallel` — the exact hazard its own docblock described but did not
     * cover, because two ordinary `php artisan test` processes share that
     * directory and every `setUp()` deleted the other one's fake disks mid-test.
     * Every fake-disk root now carries `fakeDiskToken()` in its name, so the
     * purge keeps the residue fix while staying inside its own run: a child of
     * `disks/` suffixed with somebody else's token is left alone.
     *
     * Untokenised children are still removed. Nothing writes them any more —
     * every `Storage::fake()` root is tokenised — so they are either residue
     * from a run predating this change, or planted deliberately by a test that
     * asserts this purge happens (`MediaScopingTest`).
     */
    public static function purgeTestingDisks(): void
    {
        $root = static::fakeDisksRoot();

        if (! is_dir($root)) {
            return;
        }

        foreach (static::purgeableDisks($root) as $target) {
            static::deleteTree($target);
        }

        // Succeeds only once every run has cleaned up after itself, which is the
        // point: a concurrent run's disks have to still be there.
        @rmdir($root);
    }

    /**
     * The direct children of the fake-disk root this run may delete.
     *
     * A name ending in `_test_{token}` belongs to whichever run owns that token;
     * only ours is returned. A disk literally named `…_test_…` and faked without
     * a token would be skipped here, which no disk in this project is.
     *
     * @return list<string>
     */
    private static function purgeableDisks(string $root): array
    {
        $mine    = self::TOKEN_MARKER.static::fakeDiskToken();
        $entries = @scandir($root);
        $targets = [];

        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (str_contains($entry, self::TOKEN_MARKER) && ! str_ends_with($entry, $mine)) {
                continue;
            }

            $targets[] = $root.DIRECTORY_SEPARATOR.$entry;
        }

        return $targets;
    }

    /**
     * Remove one file or directory tree, never failing a test to do it.
     *
     * The whole listing is materialised before anything is deleted. Removing
     * entries while RecursiveIteratorIterator is still descending makes it
     * re-stat directories that are already gone, which throws
     * "RecursiveDirectoryIterator::__construct(...): The system cannot find
     * the path" — trading one flaky failure for another.
     */
    private static function deleteTree(string $target): void
    {
        if (! is_dir($target) || is_link($target)) {
            @unlink($target);

            return;
        }

        $files = [];
        $dirs  = [];

        try {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($items as $item) {
                /** @var \SplFileInfo $item */
                if ($item->isDir() && ! $item->isLink()) {
                    $dirs[] = $item->getPathname();
                } else {
                    $files[] = $item->getPathname();
                }
            }
        } catch (\Throwable) {
            // A concurrent writer mutated the tree mid-listing. Whatever we did
            // manage to collect is still worth removing; cleanup must never be
            // the thing that fails a test.
        }

        foreach ($files as $file) {
            @unlink($file);
        }

        // Deepest first, so a directory is only removed once it is empty.
        usort($dirs, fn ($a, $b) => substr_count($b, DIRECTORY_SEPARATOR) <=> substr_count($a, DIRECTORY_SEPARATOR));

        foreach ($dirs as $dir) {
            @rmdir($dir);
        }

        @rmdir($target);
    }

    protected function setUp(): void
    {
        // Before the purge and before anything can call `Storage::fake()`, so
        // both agree on which roots belong to this run.
        static::fakeDiskToken();

        static::purgeTestingDisks();

        parent::setUp();
    }

    /**
     * Set a bearer token, forgetting any guard resolved for a previous identity.
     *
     * Laravel memoizes the resolved user on the guard for the lifetime of a
     * single test, so a second `withToken()` carrying a different identity is
     * silently served the *first* user. The request then reports that user's
     * access — a false 200 where the second identity should have been forbidden,
     * or a false 403 where it should have been allowed — and the test passes for
     * the wrong reason. The same memoization hides a token that was deleted
     * mid-test (logout, deactivation): the stale guard keeps answering 200.
     *
     * Individual tests used to work around this by calling
     * `$this->app->get('auth')->forgetGuards()` by hand, which only helps the
     * authors who remember. Doing it here makes the safe path the default one;
     * the manual calls that remain are harmless no-ops.
     */
    public function withToken(string $token, string $type = 'Bearer')
    {
        $this->app?->get('auth')->forgetGuards();

        return parent::withToken($token, $type);
    }
}
