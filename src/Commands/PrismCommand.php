<?php

namespace Abe\Prism\Commands;

use Illuminate\Console\Command;

class PrismCommand extends Command
{
    public $signature = 'laravel-prism';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
