<?php

/**
 * 检查 Markdown 中 PHP 代码块调用的 dcat-admin API 是否真实存在。
 *
 * 用法：
 *   php tools/skill-check/check.php [--autoload=vendor/autoload.php] [--report] <file-or-dir>...
 *
 * 默认检查 .agents/skills。发现不存在的方法时退出码为 1；--report 只输出不影响退出码。
 * 单行忽略：在该行末尾加注释 `// skill-check:ignore`；整块忽略：代码块语言写成 ```php no-check。
 */

$root = dirname(__DIR__, 2);

$options = ['autoload' => $root.'/vendor/autoload.php', 'report' => false];
$paths = [];

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--autoload=')) {
        $options['autoload'] = substr($arg, 11);
    } elseif ($arg === '--report') {
        $options['report'] = true;
    } else {
        $paths[] = $arg;
    }
}

if (! $paths) {
    $paths[] = $root.'/.agents/skills';
}

if (! is_file($options['autoload'])) {
    fwrite(STDERR, "autoload not found: {$options['autoload']}\n");
    exit(2);
}

require $options['autoload'];

// 始终以当前仓库的 src 为准：在 Composer 之后再前置注册，避免加载到其他目录的同名类。
spl_autoload_register(function ($class) use ($root) {
    if (str_starts_with($class, 'Dcat\\Admin\\')) {
        $file = $root.'/src/'.str_replace('\\', '/', substr($class, 11)).'.php';

        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);

error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__.'/Checker.php';

$checker = new SkillCheck\Checker();
$errors = [];

foreach ($paths as $path) {
    if (! file_exists($path)) {
        fwrite(STDERR, "path not found, skipped: {$path}\n");

        continue;
    }

    $files = is_dir($path)
        ? iterator_to_array(new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)),
            '/\.md$/'
        ))
        : [$path];

    foreach ($files as $file) {
        foreach ($checker->checkMarkdown((string) $file) as $error) {
            $errors[] = $error;
        }
    }
}

foreach ($errors as $error) {
    echo $error, PHP_EOL;
}

echo sprintf('%d problem(s) found.', count($errors)), PHP_EOL;

exit($errors && ! $options['report'] ? 1 : 0);
