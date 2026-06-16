<?php

namespace Dcat\Admin\Http\Controllers;

use Dcat\Admin\Admin;
use Dcat\Admin\Layout\Content;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class WebArtisanController extends Controller
{
    public function index(Content $content): Content
    {
        Admin::style(
            "@import url('https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,400;0,500;1,400&display=swap');"
        );

        return $content
            ->title('Web Artisan')
            ->description(trans('admin.menu.titles.web_artisan', [], null, 'zh_CN') ?: 'Artisan 命令控制台')
            ->body(view('admin::helpers.web-artisan', [
                'baseUrl' => url(config('admin.route.prefix').'/helpers/artisan'),
            ]));
    }

    public function commands(): JsonResponse
    {
        $php = PHP_BINARY;
        $artisan = base_path('artisan');

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open([$php, $artisan, 'list', '--format=json'], $descriptors, $pipes);

        if (! is_resource($proc)) {
            return response()->json(['error' => 'Failed to run artisan'], 500);
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($proc);

        $data = json_decode($output, true);
        if (! $data) {
            return response()->json(['error' => 'Failed to parse artisan output'], 500);
        }

        $blacklist = config('admin.helpers.artisan.command_blacklist', []);

        $commands = array_filter($data['commands'] ?? [], function ($cmd) use ($blacklist) {
            return ! in_array($cmd['name'], $blacklist, true);
        });

        return response()->json(array_values($commands));
    }

    public function run(Request $request): JsonResponse
    {
        $command = $request->input('command');
        $args    = $request->input('args', []);
        $opts    = $request->input('opts', []);

        if (! $command || ! preg_match('/^[\w:\-]+$/', $command)) {
            return response()->json(['error' => 'Invalid command'], 422);
        }

        $blacklist = config('admin.helpers.artisan.command_blacklist', []);
        if (in_array($command, $blacklist, true)) {
            return response()->json(['error' => 'Command is blacklisted'], 403);
        }

        $php     = PHP_BINARY;
        $artisan = base_path('artisan');
        $argv    = [$php, $artisan, $command];

        foreach ((array) $args as $value) {
            if ($value !== '' && $value !== null) {
                $argv[] = (string) $value;
            }
        }

        foreach ((array) $opts as $flag => $value) {
            if (! preg_match('/^[\w\-]+$/', $flag)) {
                continue;
            }
            if ($value === true || $value === '1' || $value === 'true') {
                $argv[] = '--'.$flag;
            } elseif ($value !== '' && $value !== null && $value !== false && $value !== '0' && $value !== 'false') {
                $argv[] = '--'.$flag.'='.(string) $value;
            }
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $start = microtime(true);
        $proc  = proc_open($argv, $descriptors, $pipes, base_path());

        if (! is_resource($proc)) {
            return response()->json(['error' => 'Failed to start process'], 500);
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);
        $elapsed  = round(microtime(true) - $start, 3);

        $output = $stdout;
        if ($stderr) {
            $output .= ($output ? "\n" : '').$stderr;
        }

        return response()->json([
            'output'    => $output,
            'exit_code' => $exitCode,
            'elapsed'   => $elapsed,
            'command'   => implode(' ', array_map('escapeshellarg', $argv)),
        ]);
    }

    public function runBackground(Request $request): JsonResponse
    {
        $command = $request->input('command');
        $args    = $request->input('args', []);
        $opts    = $request->input('opts', []);

        if (! $command || ! preg_match('/^[\w:\-]+$/', $command)) {
            return response()->json(['error' => 'Invalid command'], 422);
        }

        $blacklist = config('admin.helpers.artisan.command_blacklist', []);
        if (in_array($command, $blacklist, true)) {
            return response()->json(['error' => 'Command is blacklisted'], 403);
        }

        $this->cleanLogs();

        $logDir  = $this->logDir();
        $logName = 'artisan-'.date('YmdHis').'-'.getmypid().'-'.str_replace(':', '_', $command).'.log';
        $logPath = $logDir.'/'.$logName;

        $php     = PHP_BINARY;
        $artisan = escapeshellarg(base_path('artisan'));
        $parts   = [escapeshellarg($php), $artisan, escapeshellarg($command)];

        foreach ((array) $args as $value) {
            if ($value !== '' && $value !== null) {
                $parts[] = escapeshellarg((string) $value);
            }
        }

        foreach ((array) $opts as $flag => $value) {
            if (! preg_match('/^[\w\-]+$/', $flag)) {
                continue;
            }
            if ($value === true || $value === '1' || $value === 'true') {
                $parts[] = '--'.$flag;
            } elseif ($value !== '' && $value !== null && $value !== false && $value !== '0' && $value !== 'false') {
                $parts[] = '--'.$flag.'='.escapeshellarg((string) $value);
            }
        }

        $cmd = 'nohup '.implode(' ', $parts).' >> '.escapeshellarg($logPath).' 2>&1 &';
        exec($cmd);

        return response()->json([
            'log_file' => $logName,
            'message'  => 'Command started in background.',
        ]);
    }

    public function logs(): JsonResponse
    {
        $logDir = $this->logDir();
        $files  = glob($logDir.'/*.log') ?: [];

        usort($files, fn ($a, $b) => filemtime($b) - filemtime($a));

        $result = array_map(function ($path) {
            return [
                'name'     => basename($path),
                'size'     => filesize($path),
                'modified' => date('Y-m-d H:i:s', filemtime($path)),
            ];
        }, $files);

        return response()->json($result);
    }

    public function logContent(Request $request): JsonResponse
    {
        $filename = $request->input('file', '');

        if (! preg_match('/^[\w\-\.]+\.log$/', $filename)) {
            return response()->json(['error' => 'Invalid filename'], 422);
        }

        $logDir  = $this->logDir();
        $path    = $logDir.'/'.$filename;
        $realDir = realpath($logDir);
        $realPath = realpath($path);

        if (! $realPath || ! str_starts_with($realPath, $realDir.'/')) {
            return response()->json(['error' => 'File not found'], 404);
        }

        return response()->json(['content' => file_get_contents($realPath)]);
    }

    public function deleteLog(Request $request): JsonResponse
    {
        $filename = $request->input('file', '');

        if (! preg_match('/^[\w\-\.]+\.log$/', $filename)) {
            return response()->json(['error' => 'Invalid filename'], 422);
        }

        $logDir  = $this->logDir();
        $path    = $logDir.'/'.$filename;
        $realDir = realpath($logDir);
        $realPath = realpath($path);

        if (! $realPath || ! str_starts_with($realPath, $realDir.'/')) {
            return response()->json(['error' => 'File not found'], 404);
        }

        unlink($realPath);

        return response()->json(['message' => 'Deleted.']);
    }

    protected function logDir(): string
    {
        $dir = storage_path('logs/admin-artisan');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    protected function cleanLogs(): void
    {
        $logDir   = $this->logDir();
        $ttlHours = (int) config('admin.helpers.artisan.log_ttl_hours', 24);
        $maxCount = (int) config('admin.helpers.artisan.log_max_count', 50);
        $files    = glob($logDir.'/*.log') ?: [];

        $now = time();
        foreach ($files as $file) {
            if (($now - filemtime($file)) > $ttlHours * 3600) {
                unlink($file);
            }
        }

        $files = glob($logDir.'/*.log') ?: [];
        if (count($files) < $maxCount) {
            return;
        }

        usort($files, fn ($a, $b) => filemtime($a) - filemtime($b));
        $overflow = array_slice($files, 0, count($files) - $maxCount);
        foreach ($overflow as $file) {
            unlink($file);
        }
    }
}
