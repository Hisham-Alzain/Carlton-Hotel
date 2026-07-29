<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
     * Delete every fake-disk artifact left behind by a previous test or a
     * previous run.
     *
     * `Storage::fake('public')` only cleans the one disk it is given, so files
     * written to any other disk — or written by a test that crashed before its
     * assertions — survive on disk and are still there when the next run starts.
     * That residue makes storage-sensitive tests pass or fail depending on what
     * ran before them: a suite that is 432/438 on the first run and 438/438 on
     * every run after the directory is deleted by hand. A suite that fails
     * randomly trains people to ignore failures, so this runs unconditionally
     * before every test.
     *
     * Skipped under `--parallel`, where each process owns a token-suffixed root
     * that `Storage::fake()` already cleans; a blanket purge there would let one
     * process delete another's disk mid-test.
     */
    public static function purgeTestingDisks(): void
    {
        if (($_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? null) !== null) {
            return;
        }

        $root = static::fakeDisksRoot();

        if (! is_dir($root)) {
            return;
        }

        // Materialise the whole listing before deleting anything. Removing
        // entries while RecursiveIteratorIterator is still descending makes it
        // re-stat directories that are already gone, which throws
        // "RecursiveDirectoryIterator::__construct(...): The system cannot find
        // the path" — trading one flaky failure for another.
        $files = [];
        $dirs  = [];

        try {
            $items = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
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

        @rmdir($root);
    }

    protected function setUp(): void
    {
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
