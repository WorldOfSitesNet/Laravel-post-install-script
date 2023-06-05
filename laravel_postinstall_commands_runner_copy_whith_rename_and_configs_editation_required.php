<?php

// Put this script into a root directory of project
// Copy with rename script like "script.php"
// Read all the comments on the code all the way to the end of the script
// Change the settings and commands in the arrays below
// Then run command in console: php script.php

ini_set('memory_limit', '-1');

// Path to the Laravel project root folder
$projectPath = __DIR__;

// List of commands to execute
// Uncomment the commands below if they need to be executed
$commands = [
    // Laravel installation
    // "composer create-project --prefer-dist laravel/laravel $projectPath",
    // "cd $projectPath",

    // Git initialization and configuration
    // 'git init',
    // 'git config user.name "Your Name"',
    // 'git config user.email "your-email@example.com"',

    // Clone project from GitHub (alternative to Laravel installation)
    // 'git clone [repository_url] $projectPath',
    // "cd $projectPath",

    // Add remote origin (if using Git clone)
    // 'git remote add origin [repository_url]',

    // Branching and switching
    /* 'git checkout -b dev',
    'git branch -m main',
    'git branch -m dev',
    'git branch -m laravel_install', */

    // Laravel setup commands (after installation or cloning)
    'composer install',
    // 'cp .env.example .env', // For Linux OS
    'copy .env.example .env', // For Windows OS
    'php artisan key:generate',
    'php artisan migrate',
    // 'php artisan serve' // if needed
];

// Configuration values for the database connection
$dbConfigValues = [
    'APP_NAME' => 'YourAppName', // or comment out this line to leave the default value 'Laravel'
    'APP_URL' => 'http://your-domain.tld/', // or comment out this line to leave the default value 'http://localhost'
    'DB_HOST' => 'localhost',
    'DB_PORT' => '3306',
    'DB_DATABASE' => 'your_db_name',
    'DB_USERNAME' => 'your_db_user_name',
    'DB_PASSWORD' => 'your_db_password',
];

/* Script to rollback changes in case of failure in the previous run */

// Delete the .env file if it was left over from the last failed attempt
$envFilePath = "$projectPath/.env";
if (file_exists($envFilePath)) {
    unlink($envFilePath);
}

// Remove vendor directory if it was left over from the last failed attempt
$vendorPath = "$projectPath/vendor";
if (is_dir($vendorPath)) {
    removeDirectory($vendorPath);
}

/**
 * Recursively remove a directory and its contents.
 *
 * @param string $directory The directory path.
 */
function removeDirectory($directory)
{
    $os = php_uname('s');
    if (strpos($os, 'Windows') !== false) {
        // Windows OS
        $command = "rmdir /s /q " . escapeshellarg($directory);
    } else {
        // Unix-like OS (Linux, macOS, etc.)
        $command = "rm -rf " . escapeshellarg($directory);
    }
    shell_exec($command);
}

// Search for the migration file and update it
$migrationDirectory = "$projectPath/database/migrations";
$partialFilename = 'create_users_table.php';

// Find the migration file
$files = scandir($migrationDirectory);
foreach ($files as $file) {
    if ($file !== '.' && $file !== '..' && is_file($migrationDirectory . '/' . $file) && str_contains($file, $partialFilename)) {
        $migrationFilePath = $migrationDirectory . '/' . $file;
        $migrationContent = file_get_contents($migrationFilePath);

        // Check if the migration file contains the content to replace
        if (strpos($migrationContent, 'Schema::defaultStringLength(191);') !== false) {
            // Remove the line containing Schema::defaultStringLength(191) and the next empty line
            $updatedMigrationContent = preg_replace('/Schema::defaultStringLength\(191\);\s*\R?\n?/', '', $migrationContent);

            // Write the updated migration content back to the file
            file_put_contents($migrationFilePath, $updatedMigrationContent);
        }
    }
}

/* End of rollback */

// Variable to track if any command failed
$hasFailed = false;

// Go through the commands and execute them
foreach ($commands as $command) {
    // Creating a command process
    $process = proc_open("cd $projectPath && $command", [], $pipes);

    // Waiting for the command to complete
    while (($status = proc_get_status($process)) && $status['running']) {
        // Pause 1000 milliseconds
        usleep(1000000);
    }

    // Get the exit code of the command
    $exitCode = proc_close($process);

    if (strpos($command, 'composer create-project') !== false) {
        // Laravel installation command

        // Check if the command failed (exit code other than 0)
        if ($exitCode !== 0) {
            // Command failed
            $hasFailed = true;
            echo "Command failed: $command\n";
        }
    } elseif (strpos($command, 'git ') !== false) {
        // Git commands

        // Check if the command failed (exit code other than 0)
        if ($exitCode !== 0) {
            // Command failed
            $hasFailed = true;
            echo "Command failed: $command\n";
        }
    } else {

        if ($command === 'cp .env.example .env' || $command === 'copy .env.example .env') {

            // Update the database configuration in the .env file
            $envFilePath = "$projectPath/.env";
            $envContent = file_get_contents($envFilePath);

            // Check if the command failed (exit code other than 0)
            if ($exitCode !== 0) {
                // Command failed
                $hasFailed = true;
                echo "Command failed: $command\n";
            }

            // Parse the .env file as an INI format
            $envConfig = parse_ini_string($envContent, true, INI_SCANNER_TYPED);

            // Check if parsing was successful
            if ($envConfig === false) {
                $hasFailed = true;
                echo "Failed to parse the .env file.\n";
                exit(1); // Terminate the script with a non-zero exit code
            }

            // Update the database configuration values
            foreach ($dbConfigValues as $key => $value) {
                $envConfig[$key] = $value;
            }

            // Generate the updated .env content
            $updatedEnvContent = '';
            foreach ($envConfig as $key => $value) {
                $updatedEnvContent .= "$key=$value\n";
            }

            // Write the updated .env content back to the file
            file_put_contents($envFilePath, $updatedEnvContent);

            // Search for the migration file and update it
            $migrationDirectory = "$projectPath/database/migrations";
            $partialFilename = 'create_users_table.php';

            // Create PDO instance
            $dsn = "mysql:host={$dbConfigValues['DB_HOST']};port={$dbConfigValues['DB_PORT']};dbname={$dbConfigValues['DB_DATABASE']};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbConfigValues['DB_USERNAME'], $dbConfigValues['DB_PASSWORD']);

            // Check if the PDO connection was successful
            if (!$pdo) {
                $hasFailed = true;
                echo "Failed to connect to the database.\n";
                exit(1); // Terminate the script with a non-zero exit code
            }

            // Set the SQL mode to disable ONLY_FULL_GROUP_BY
            $pdo->exec("SET SESSION sql_mode = ''");

            // Delete all existing tables in the database
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

            $serverVersion = $pdo->query('SELECT VERSION() AS version')->fetchColumn();
            $majorVersion = (int) explode('.', $serverVersion)[0];

            // Check if the server version is 8 or higher and if the partial filename matches
            if ($majorVersion >= 8 && str_contains($partialFilename, 'create_users_table.php')) {
                // Find the migration file
                $files = scandir($migrationDirectory);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($migrationDirectory . '/' . $file) && str_contains($file, $partialFilename)) {
                        $migrationContent = file_get_contents($migrationDirectory . '/' . $file);

                        // Check if the migration file contains the content to replace
                        if (strpos($migrationContent, 'Schema::create(\'users\', function (Blueprint $table) {') !== false) {
                            // Insert the line before creating the users table
                            $updatedMigrationContent = str_replace('Schema::create(\'users\', function (Blueprint $table) {', "Schema::defaultStringLength(191);\n\n        Schema::create('users', function (Blueprint \$table) {", $migrationContent);

                            // Write the updated migration content back to the file
                            file_put_contents($migrationDirectory . '/' . $file, $updatedMigrationContent);
                        }
                    }
                }
            }
        }
    }
}

// Check if any command failed
if ($hasFailed) {
    echo "\e[0;31m" . "Task completed with errors." . "\e[0m" . "\n";
} else {
    echo "\e[0;32m" . "Task completed successfully." . "\e[0m" . "\n";

    // Self-destruction of the script after successful execution
    unlink(__FILE__); // Comment out this line for testing in the development mode of the new functionality
}
