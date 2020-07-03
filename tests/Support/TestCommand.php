<?php

namespace Tests\Support;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestCommand extends Command
{
    protected $signature = 'test:command';
    protected $description = 'Do some testy stuff';

    public function handle()
    {
        Log::debug('did something testy');
    }
}
