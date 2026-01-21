<?php

namespace Realodix\Haiku\Test\Feature;

use Illuminate\Container\Container;
use Realodix\Haiku\Cache\Cache;
use Realodix\Haiku\Cache\Repository;
use Realodix\Haiku\Enums\Mode;
use Realodix\Haiku\Test\TestCase;
use Symfony\Component\Filesystem\Path;

class CacheTest extends TestCase
{
    private Cache $cache;

    private Repository $repository;

    private string $testCacheFile;

    protected function setUp(): void
    {
        $container = Container::getInstance();
        $container->flush();

        parent::setUp();

        $this->cache = $container->build(Cache::class);
        $this->repository = $this->cache->repository();
        $this->testCacheFile = Path::join($this->tmpDir, '.test_cache.json');
    }

    protected function tearDown(): void
    {
        if ($this->fs->exists($this->testCacheFile)) {
            $this->fs->remove($this->testCacheFile);
        }

        parent::tearDown();
    }

    public function testResolveCachePathAllScenarios(): void
    {
        $baseDir = Path::join($this->tmpDir, 'ResolveCachePath');
        $defaultCacheFile = Repository::DEFAULT_FILENAME;

        $cases = [
            // Input, Expected output (relative to baseDir if applicable)
            [null, $defaultCacheFile],
            ['', Path::join($baseDir, $defaultCacheFile)],
            ['custom_cache.json', Path::join($baseDir, 'custom_cache.json')],
            ['cache_dir', Path::join($baseDir, 'cache_dir', $defaultCacheFile)],
            ['cache_dir/', Path::join($baseDir, 'cache_dir', $defaultCacheFile)],
            ['.tmp', Path::join($baseDir, '.tmp', $defaultCacheFile)],
            ['.tmp/', Path::join($baseDir, '.tmp', $defaultCacheFile)],
            ['.tmp/abc.json', Path::join($baseDir, '.tmp', 'abc.json')],
            ['.tmp/abc.txt', Path::join($baseDir, '.tmp', 'abc.txt')],
        ];

        foreach ($cases as [$input, $expected]) {
            // 1. Make sure environment is clean before each case
            $this->fs->remove($baseDir);
            $this->fs->mkdir($baseDir);

            // 2. Prepend baseDir for relative paths
            $inputPath = $input === null ? null : Path::join($baseDir, $input);

            // 3. Assertion
            $actual = $this->callPrivateMethod($this->cache, 'resolvePath', [$inputPath]);
            $this->assertSame($expected, $actual, 'Failed for input: '.var_export($input, true));
        }

        // 6. Cleanup (optional safety)
        if ($this->fs->exists($baseDir)) {
            $this->fs->remove($baseDir);
        }
    }

    // Test basic cache operations
    public function testSetAndGetCache(): void
    {
        $key = 'testKey';
        $data = ['foo' => 'bar'];
        $this->repository->set($key, $data);
        $this->assertSame($data, $this->repository->get($key));
    }

    public function testGetAllCache(): void
    {
        $this->repository->set('key1', ['data1']);
        $this->repository->set('key2', ['data2']);
        $this->assertSame(['key1' => ['data1'], 'key2' => ['data2']], $this->repository->all());
    }

    public function testRemoveCacheEntry(): void
    {
        $key = 'testKey';
        $this->repository->set($key, ['foo' => 'bar']);
        $this->repository->remove($key);
        $this->assertNull($this->repository->get($key));
    }

    public function testClearCache(): void
    {
        $this->repository->set('key1', ['data1']);
        $this->repository->clear();
        $this->assertEmpty($this->repository->all());
    }

    public function testSaveAndLoadCache(): void
    {
        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->set('key1', ['data1']);
        $this->repository->save();

        $newRepository = $this->repository;
        $newRepository->setCacheFile($this->testCacheFile);
        $newRepository->load();

        $this->assertSame(['key1' => ['data1']], $newRepository->all());
    }

    public function testLoadNonExistentCacheFile(): void
    {
        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->load(); // Should not throw error
        $this->assertEmpty($this->repository->all());
    }

    // Test prepareForRun scenarios
    public function testPrepareForRunWithNoForceAndNoExistingCache(): void
    {
        $this->cache->prepareForRun([], $this->testCacheFile, Mode::Default);
        $this->assertEmpty($this->repository->all());
    }

    public function testPrepareForRunWithNoForceAndExistingCache(): void
    {
        // Create a dummy cache file with a stale entry
        $staleFile = Path::join($this->tmpDir, 'non_existent_file.txt');
        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->set($staleFile, ['data' => 'stale']);
        $this->repository->save();

        // Ensure the stale file does not exist
        if ($this->fs->exists($staleFile)) {
            $this->fs->remove($staleFile);
        }

        $this->cache->prepareForRun([], $this->testCacheFile, Mode::Default);
        $this->assertEmpty($this->repository->all()); // Stale entry should be cleaned
    }

    public function testPrepareForRunWithForceAndNoExistingCache(): void
    {
        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->set('key1', ['data1']); // Add some data
        $this->repository->save();

        $this->cache->prepareForRun([], $this->testCacheFile, Mode::Force);
        $this->assertEmpty($this->repository->all()); // Cache should be cleared
        // $this->assertFalse($this->fs->exists($this->testCacheFile)); // Cache file should be removed
    }

    public function testPrepareForRunWithForceAndExistingCache(): void
    {
        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->set('key1', ['data1']);
        $this->repository->save();

        $this->cache->prepareForRun([], $this->testCacheFile, Mode::Force);
        $this->assertEmpty($this->repository->all());
        // $this->assertFalse($this->fs->exists($this->testCacheFile));
    }

    public function testPrepareForRunWithForceMultipleTimes(): void
    {
        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->set('key1', ['data1']);
        $this->repository->save();

        $this->cache->prepareForRun([], $this->testCacheFile, Mode::Force);
        $this->assertEmpty($this->repository->all());
        // $this->assertFalse($this->fs->exists($this->testCacheFile));

        // Add some data again, then call prepareForRun with force again
        $this->repository->set('key2', ['data2']);
        $this->repository->save();

        $this->cache->prepareForRun([], $this->testCacheFile, Mode::Force);
        $this->assertNotEmpty($this->repository->all()); // Should not clear again
        $this->assertTrue($this->fs->exists($this->testCacheFile));
    }

    public function testCleanStaleEntriesRemovesNonExistentFiles(): void
    {
        $existingFile = Path::join($this->tmpDir, 'existing_file.txt');
        $staleFile = Path::join($this->tmpDir, 'non_existent_file.txt');

        $this->fs->dumpFile($existingFile, 'content');

        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->set($existingFile, ['data' => 'exists']);
        $this->repository->set($staleFile, ['data' => 'stale']);
        $this->repository->save();

        $this->callPrivateMethod($this->cache, 'cleanStaleEntries', [[$existingFile]]);

        $loadedCache = $this->repository->all();
        $this->assertArrayHasKey($existingFile, $loadedCache);
        $this->assertArrayNotHasKey($staleFile, $loadedCache);
        $this->assertTrue($this->fs->exists($this->testCacheFile)); // Cache file should still exist

        // Verify the cache file content after cleaning
        $newRepository = $this->repository;
        $newRepository->setCacheFile($this->testCacheFile);
        $newRepository->load();
        $this->assertArrayHasKey($existingFile, $newRepository->all());
        $this->assertArrayNotHasKey($staleFile, $newRepository->all());
    }

    public function testCleanStaleEntriesDoesNotRemoveExistingFiles(): void
    {
        $existingFile = Path::join($this->tmpDir, 'existing_file.txt');
        $this->fs->dumpFile($existingFile, 'content');

        $this->repository->setCacheFile($this->testCacheFile);
        $this->repository->set($existingFile, ['data' => 'exists']);
        $this->repository->save();

        $this->callPrivateMethod($this->cache, 'cleanStaleEntries', [[$existingFile]]);

        $loadedCache = $this->repository->all();
        $this->assertArrayHasKey($existingFile, $loadedCache);
        $this->assertTrue($this->fs->exists($this->testCacheFile));
    }

    /**
     * @see Realodix\Haiku\Builder\Builder::class
     * @see Realodix\Haiku\Cache\Cache::cleanStaleEntries()
     */
    public function testCleanStaleEntriesForBuilder(): void
    {
        $this->repository->setCacheFile($this->testCacheFile);

        // 1. Define output paths
        $activePath1 = Path::join($this->tmpDir, 'active_list_1.txt');
        $activePath2 = Path::join($this->tmpDir, 'active_list_2.txt');
        $stalePath = Path::join($this->tmpDir, 'stale_list.txt');

        $this->fs->touch([$activePath1, $activePath2]);

        // This is the array that the old implementation expected
        $activeOutputFiles = [$activePath1, $activePath2];

        // 2. Set initial cache state
        $this->repository->set($activePath1, ['data' => 'active 1']);
        $this->repository->set($activePath2, ['data' => 'active 2']);
        $this->repository->set($stalePath, ['data' => 'stale']);
        $this->repository->save();

        // 3. Run the method to be tested, passing the array of active files
        $this->callPrivateMethod($this->cache, 'cleanStaleEntries', [$activeOutputFiles]);

        // 4. Assertions
        $loadedCache = $this->repository->all();
        $this->assertArrayHasKey($activePath1, $loadedCache, 'Active entry 1 should not be removed.');
        $this->assertArrayHasKey($activePath2, $loadedCache, 'Active entry 2 should not be removed.');
        $this->assertArrayNotHasKey($stalePath, $loadedCache, 'Stale entry should be removed.');

        // Verify the cache file content after cleaning
        $newRepository = new Repository($this->fs);
        $newRepository->setCacheFile($this->testCacheFile);
        $newRepository->load();
        $this->assertArrayHasKey($activePath1, $newRepository->all());
        $this->assertArrayNotHasKey($stalePath, $newRepository->all());
    }
}
