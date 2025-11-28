<?php

/**
 * Web-based Setup Script for cPanel
 * Upload this to your cPanel public_html and visit it in browser
 * Delete this file after setup is complete!
 */

// Security: Only allow from specific IP or use a secret key
$SETUP_KEY = 'CHANGE_THIS_SECRET'; // Change this to a random string
if (!isset($_GET['key']) || $_GET['key'] !== $SETUP_KEY) {
    die('Access denied. Add ?key=YOUR_SECRET to URL');
}

set_time_limit(300);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Directory paths for your cPanel setup
// Adjust these if your directory structure is different
$homeDir = '/home/tautlogy';
$baseDir = $homeDir . '/symfony/task4_php';  // Application code directory
$publicDir = $homeDir . '/public_html/task4_php';  // Public files directory

$errors = [];
$messages = [];

// Helper function
function runCommand($command, $cwd = null)
{
    global $baseDir;
    $cwd = $cwd ?: $baseDir;

    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w']
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);

    if (is_resource($process)) {
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $returnValue = proc_close($process);

        return [
            'output' => $output,
            'error' => $error,
            'code' => $returnValue
        ];
    }

    return ['output' => '', 'error' => 'Failed to run command', 'code' => 1];
}

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Symfony Setup - cPanel</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
        }

        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }

        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
        }

        pre {
            background: #f4f4f4;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }

        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: #0056b3;
        }

        h1 {
            color: #333;
        }

        h2 {
            color: #555;
            margin-top: 30px;
        }

        .step {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>

<body>
    <h1>🚀 Symfony App Setup for cPanel</h1>

    <div class="info">
        <strong>⚠️ Important:</strong> Delete this file (setup.php) after setup is complete!
    </div>

    <?php
    $step = $_GET['step'] ?? 'check';

    // Step 1: Check Requirements
    if ($step === 'check') {
        echo '<div class="step">';
        echo '<h2>Step 1: System Check</h2>';

        $checks = [
            'PHP Version >= 8.1' => version_compare(PHP_VERSION, '8.1.0', '>='),
            'PDO Extension' => extension_loaded('pdo'),
            'PDO PostgreSQL' => extension_loaded('pdo_pgsql'),
            'Intl Extension' => extension_loaded('intl'),
            'Zip Extension' => extension_loaded('zip'),
            'Composer Installed' => file_exists($baseDir . '/vendor/autoload.php') || is_executable('/usr/local/bin/composer'),
            'Writable var/ directory' => is_writable($baseDir . '/var') || mkdir($baseDir . '/var', 0755, true),
        ];

        $allPassed = true;
        foreach ($checks as $check => $passed) {
            $status = $passed ? '✅' : '❌';
            $class = $passed ? 'success' : 'error';
            echo "<div class='$class'>$status $check</div>";
            if (!$passed) $allPassed = false;
        }

        echo '<p>Current PHP Version: ' . PHP_VERSION . '</p>';
        echo '<p>App Base Directory: ' . $baseDir . '</p>';
        echo '<p>Public Directory: ' . $publicDir . '</p>';

        if ($allPassed) {
            echo '<a href="?key=' . $SETUP_KEY . '&step=composer" class="btn">Continue to Step 2</a>';
        } else {
            echo '<div class="error">Please fix the issues above before continuing.</div>';
        }
        echo '</div>';
    }

    // Step 2: Install Composer Dependencies
    if ($step === 'composer') {
        echo '<div class="step">';
        echo '<h2>Step 2: Install Dependencies</h2>';

        $composerPath = '/usr/local/bin/composer';
        if (!is_executable($composerPath)) {
            $composerPath = 'composer'; // Try global
        }

        echo '<div class="info">Installing Composer dependencies... This may take a few minutes.</div>';

        $result = runCommand("$composerPath install --no-dev --optimize-autoloader --no-interaction 2>&1");

        if ($result['code'] === 0) {
            echo '<div class="success">✅ Dependencies installed successfully!</div>';
            echo '<pre>' . htmlspecialchars($result['output']) . '</pre>';
            echo '<a href="?key=' . $SETUP_KEY . '&step=env" class="btn">Continue to Step 3</a>';
        } else {
            echo '<div class="error">❌ Failed to install dependencies</div>';
            echo '<pre>' . htmlspecialchars($result['output'] . "\n" . $result['error']) . '</pre>';
        }
        echo '</div>';
    }

    // Step 3: Configure Environment
    if ($step === 'env') {
        echo '<div class="step">';
        echo '<h2>Step 3: Configure Environment</h2>';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $envContent = "APP_ENV=prod\n";
            $envContent .= "APP_SECRET=" . $_POST['app_secret'] . "\n";
            $envContent .= "DATABASE_URL=" . $_POST['database_url'] . "\n";
            $envContent .= "MAILER_DSN=" . $_POST['mailer_dsn'] . "\n";
            $envContent .= "MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0\n";

            if (file_put_contents($baseDir . '/.env.local', $envContent)) {
                echo '<div class="success">✅ Environment configured successfully!</div>';
                echo '<a href="?key=' . $SETUP_KEY . '&step=database" class="btn">Continue to Step 4</a>';
            } else {
                echo '<div class="error">❌ Failed to write .env.local file</div>';
            }
        } else {
    ?>
            <form method="POST">
                <p><label>App Secret (random 64 chars): <br><input type="text" name="app_secret" value="<?= bin2hex(random_bytes(32)) ?>" size="70" required></label></p>
                <p><label>Database URL: <br><input type="text" name="database_url" placeholder="postgresql://user:pass@localhost:5432/dbname" size="70" required></label></p>
                <p><label>Mailer DSN: <br><input type="text" name="mailer_dsn" placeholder="smtp://user:pass@smtp.example.com:587?encryption=tls" size="70" required></label></p>
                <button type="submit" class="btn">Save Configuration</button>
            </form>
    <?php
        }
        echo '</div>';
    }

    // Step 4: Database Migration
    if ($step === 'database') {
        echo '<div class="step">';
        echo '<h2>Step 4: Setup Database</h2>';

        $result = runCommand('php bin/console doctrine:migrations:migrate --no-interaction 2>&1');

        if ($result['code'] === 0) {
            echo '<div class="success">✅ Database migrated successfully!</div>';
            echo '<pre>' . htmlspecialchars($result['output']) . '</pre>';
            echo '<a href="?key=' . $SETUP_KEY . '&step=assets" class="btn">Continue to Step 5</a>';
        } else {
            echo '<div class="error">❌ Database migration failed</div>';
            echo '<pre>' . htmlspecialchars($result['output'] . "\n" . $result['error']) . '</pre>';
            echo '<a href="?key=' . $SETUP_KEY . '&step=env" class="btn">Back to Configuration</a>';
        }
        echo '</div>';
    }

    // Step 5: Build Assets
    if ($step === 'assets') {
        echo '<div class="step">';
        echo '<h2>Step 5: Build Assets</h2>';

        $result = runCommand('php bin/console tailwind:build 2>&1');
        echo '<div class="info">Building Tailwind CSS...</div>';
        echo '<pre>' . htmlspecialchars($result['output']) . '</pre>';

        $result = runCommand('php bin/console cache:clear --env=prod 2>&1');
        echo '<div class="info">Clearing cache...</div>';
        echo '<pre>' . htmlspecialchars($result['output']) . '</pre>';

        echo '<div class="success">✅ Assets built successfully!</div>';
        echo '<a href="?key=' . $SETUP_KEY . '&step=final" class="btn">Continue to Final Step</a>';
        echo '</div>';
    }

    // Final Step
    if ($step === 'final') {
        echo '<div class="step">';
        echo '<h2>🎉 Setup Complete!</h2>';

        echo '<div class="success">';
        echo '<h3>Next Steps:</h3>';
        echo '<ol>';
        echo '<li><strong>Delete this file:</strong> Delete setup.php from ~/public_http/task4_php/</li>';
        echo '<li><strong>Document Root is already set:</strong> Your public files are in: <code>' . $publicDir . '</code></li>';
        echo '<li><strong>Setup Cron Job:</strong> Add this cron job in cPanel to process emails:<br>';
        echo '<pre>*/5 * * * * cd ' . $baseDir . ' && /usr/bin/php bin/console messenger:consume async --time-limit=300 >> ' . $baseDir . '/var/log/messenger.log 2>&1</pre></li>';
        echo '<li><strong>Visit your app:</strong> Go to your domain to see the application</li>';
        echo '</ol>';
        echo '</div>';

        echo '<div class="info">';
        echo '<h3>Application Info:</h3>';
        echo '<p>Application Directory: ' . $baseDir . '</p>';
        echo '<p>Public Directory: ' . $publicDir . '</p>';
        echo '<p>PHP Version: ' . PHP_VERSION . '</p>';
        echo '</div>';

        echo '</div>';
    }
    ?>

    <hr>
    <p style="color: #999;">Setup Tool v1.0 | Current Step: <?= htmlspecialchars($step) ?></p>
</body>

</html>