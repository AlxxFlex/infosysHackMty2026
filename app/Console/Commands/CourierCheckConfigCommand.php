<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\CourierConfigValidator;
use Illuminate\Console\Command;

final class CourierCheckConfigCommand extends Command
{
    protected $signature = 'courier:check-config';

    protected $description = 'Validate Courier AI runtime configuration and optional provider modes.';

    public function handle(CourierConfigValidator $validator): int
    {
        $errors = $validator->errors();
        if ($errors === []) {
            $this->info('Courier AI configuration is valid. Optional services may remain disabled.');

            return self::SUCCESS;
        }

        $this->error('Courier AI configuration needs attention:');
        foreach ($errors as $error) {
            $this->line(' - '.$error);
        }

        return self::FAILURE;
    }
}
