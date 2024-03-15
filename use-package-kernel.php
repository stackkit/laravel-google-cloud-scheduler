<?php

file_put_contents(
    __DIR__.'/vendor/orchestra/testbench-core/src/Console/Commander.php',
    str_replace(
        'use Illuminate\Contracts\Console\Kernel as ConsoleKernel',
        'use Workbench\App\Console\Kernel as ConsoleKernel',
        file_get_contents(__DIR__.'/vendor/orchestra/testbench-core/src/Console/Commander.php')
    )
);
