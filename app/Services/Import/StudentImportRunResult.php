<?php

namespace App\Services\Import;

class StudentImportRunResult
{
    public function __construct(
        public int $created = 0,
        public int $updated = 0,
        public int $skipped = 0,
        public int $failed = 0,
    ) {}
}
