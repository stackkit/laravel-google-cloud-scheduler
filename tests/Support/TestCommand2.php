<?php

namespace Tests\Support;

use Illuminate\Console\Command;

class TestCommand2 extends Command
{
    protected $signature = 'test:command2';

    protected $description = 'Do some testy stuff';

    public function handle()
    {
        logger('did something testy');
    }
}
