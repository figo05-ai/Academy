<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeServiceCommand extends Command
{
    protected $signature = 'make:service {name : The name of the service class}';

    protected $description = 'Create a new business logic service class';

    public function handle()
    {
        $name = $this->argument('name');

        $className = ucfirst($name);
        if (!str_ends_with($className, 'Service')) {
            $className .= 'Service';
        }

        $directory = app_path('Services');
        $filePath = "{$directory}/{$className}.php";

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($filePath)) {
            $this->error("Service {$className} already exists!");
            return Command::FAILURE;
        }

        $stub = "<?php\n\nnamespace App\Services;\n\nclass {$className}\n{\n    public function __construct()\n    {\n        //\n    }\n}\n";

        File::put($filePath, $stub);

        $this->info("Service {$className} created successfully at app/Services/{$className}.php");
        return Command::SUCCESS;
    }
}
