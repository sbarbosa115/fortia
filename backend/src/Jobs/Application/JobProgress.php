<?php

namespace App\Jobs\Application;

/** Lets a running job publish its visible sub-stage (e.g. styles: reading_website → designing_styles → saving). */
interface JobProgress
{
    public function jobId(): string;

    public function stage(string $stage): void;
}
