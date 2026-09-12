<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Vite;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Some tests (see AnalyticsTest) read the built asset manifest
        // (public/build/manifest.json) directly and assert the rendered
        // page references that exact hashed filename. Laravel's Vite
        // integration ignores the manifest and emits dev-server URLs
        // instead whenever public/hot exists - regardless of whether a
        // dev server is actually running right now. A public/hot left
        // behind by an interrupted `npm run dev`/`composer dev` session
        // elsewhere would otherwise make asset resolution during tests
        // depend on that ambient, unrelated process state. Pointing at a
        // path that can never exist keeps every test resolving assets
        // from the manifest, deterministically, every time.
        app(Vite::class)->useHotFile(storage_path('framework/testing/vite-hot-disabled'));
    }
}
