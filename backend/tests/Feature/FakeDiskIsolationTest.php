<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Two `php artisan test` processes must not delete each other's fake disks.
 *
 * `TestCase::purgeTestingDisks()` used to remove `storage/framework/testing/disks`
 * whole at every `setUp()`, guarded only by `TEST_TOKEN` — which is set under
 * `--parallel` and nowhere else. Two plain runs therefore shared one directory
 * and each one's `setUp()` deleted the other's uploads mid-test, which is what
 * made `MediaLibraryTest`, `GalleryTest` and `MediaCleanupTest` fail in a full
 * run and pass on their own. `Storage::fake('public')` compounded it: it cleans
 * the root it is handed, and without a token that root is shared too.
 *
 * The two halves of the fix are asserted separately below, in-process, so a
 * regression shows up in the ordinary suite rather than only when somebody
 * happens to run two terminals at once.
 */
class FakeDiskIsolationTest extends TestCase
{
    /** @var list<string> */
    private array $planted = [];

    protected function tearDown(): void
    {
        // Directories belonging to a "concurrent run" are skipped by the purge
        // on purpose, so this test has to take its own props away.
        foreach ($this->planted as $path) {
            if (is_dir($path)) {
                foreach ((array) glob($path.DIRECTORY_SEPARATOR.'*') as $file) {
                    @unlink((string) $file);
                }

                @rmdir($path);
            }
        }

        parent::tearDown();
    }

    /**
     * The token this run publishes for `Storage::fake()` to suffix its roots
     * with, read off the environment rather than through `fakeDiskToken()`.
     *
     * Deliberately not the accessor: a test that calls a method the broken
     * version does not have fails with "undefined method", which proves an API
     * is missing rather than that the isolation works. Read this way, the
     * assertions below fail on behaviour — no token published, one shared root.
     */
    private function runToken(): string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? $_ENV['TEST_TOKEN'] ?? '';

        return is_string($token) ? $token : '';
    }

    private function plant(string $directory, string $file = 'kept.bin'): string
    {
        $path = static::fakeDisksRoot().DIRECTORY_SEPARATOR.$directory;

        @mkdir($path, 0777, true);
        file_put_contents($path.DIRECTORY_SEPARATOR.$file, 'x');

        $this->planted[] = $path;

        return $path.DIRECTORY_SEPARATOR.$file;
    }

    /**
     * Half one: the root a fake disk lives in is private to this process.
     *
     * Without the token, `Storage::fake('public')` roots at `disks/public` and
     * its own `cleanDirectory()` wipes whatever a concurrent run has just
     * written there.
     */
    public function test_every_fake_disk_is_rooted_in_this_runs_own_directory(): void
    {
        $token = $this->runToken();

        $this->assertNotSame(
            '',
            $token,
            'this run published no fake-disk token, so every Storage::fake() root is shared machine-wide',
        );

        Storage::fake('public');

        $this->assertStringContainsString(
            '_test_'.$token,
            Storage::disk('public')->path('probe.jpg'),
            'the public fake disk is rooted in a directory every other test process shares',
        );
    }

    /**
     * Half two: the purge still clears residue, but only its own run's.
     *
     * All three claims are asserted in one test because they are one rule —
     * "delete what this run owns, and nothing else" — and splitting them would
     * let a purge that deletes everything pass two thirds of the file.
     */
    public function test_the_purge_clears_this_runs_residue_and_leaves_a_concurrent_runs_alone(): void
    {
        $theirs    = $this->plant('public_test_someone-elses-run');
        $ours      = $this->plant('local_test_'.$this->runToken());
        $untokened = $this->plant('stale-disk-from-a-run-before-the-token');

        static::purgeTestingDisks();

        $this->assertFileExists(
            $theirs,
            'the purge deleted a concurrent run\'s fake disk — the failure mode this guards',
        );

        $this->assertFileDoesNotExist(
            $ours,
            'the purge skipped a disk this run owns, so residue survives into the next test',
        );

        $this->assertFileDoesNotExist(
            $untokened,
            'untokenised residue survived, which is the stale-file bug the purge exists for',
        );
    }
}
