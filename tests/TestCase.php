<?php

declare(strict_types=1);

namespace Tests;

use App\Services\DB;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected bool $useDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->useDatabase) {
            $this->setUpDatabase();
        }
    }

    protected function setUpDatabase(): void
    {
        DB::init();
    }
}
