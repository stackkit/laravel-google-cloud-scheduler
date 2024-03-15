<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;

class TestCommand extends Command
{
    public $signature = 'test:command';

    public function handle()
    {
        file_put_contents(storage_path('log.txt'), 'TestCommand'.PHP_EOL, FILE_APPEND);
    }
}
