<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$directories = ['src', 'tests', 'migrations', 'config', 'public_html', 'tools'];
$errors = [];

foreach ($directories as $directory) {
    $path = $root . DIRECTORY_SEPARATOR . $directory;

    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $command = sprintf('"%s" -l "%s"', PHP_BINARY, $file->getPathname());
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $errors[] = implode(PHP_EOL, $output);
        }
    }
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo 'PHP syntax check passed.' . PHP_EOL;
