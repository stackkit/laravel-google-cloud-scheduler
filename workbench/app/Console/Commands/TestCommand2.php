<?php

namespace Workbench\App\Console\Commands;

use Illuminate\Console\Command;

class TestCommand2 extends Command
{
    public $signature = 'test:command2';

    public function handle()
    {
        file_put_contents(storage_path('log.txt'), 'TestCommand2' . PHP_EOL, FILE_APPEND);
    }
}
