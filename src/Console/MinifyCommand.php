<?php

namespace Dcat\Admin\Console;

use Dcat\Admin\Support\Helper;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class MinifyCommand extends Command
{
    const ALL = 'all';
    const DEFAULT = 'default';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:minify {name} 
        {--color= : Theme color code} 
        {--publish : Publish assets files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Minify the CSS and JS';

    /**
     * @var array
     */
    protected $colors = [
        self::DEFAULT => '',
        'blue'        => '#6d8be6',
        'blue-light'  => '#62a8ea',
        'green'       => '#4e9876',
        'indigo'      => '#4f5fba',
        'navy'        => '#2e68ff',
        'emerald'     => '#0fa868',
        'amber'       => '#f26b1d',
        'midnight'    => '#4cc9f0',
    ];

    /**
     * 主题方案中「主色」之外的变量，构建时写入 `theme/_scheme.scss`.
     *
     * 键与 $colors 的主题名对应；未列出的键写 null（= 该主题不使用这个变量）。
     *
     * @var array
     */
    protected $schemes = [
        'indigo' => [
            'body_bg'   => '#f8f9fa',
            'font_color' => '#1f2430',
            'tint'      => '#eff0ff',
            'accent'    => '#8b8cf5',
        ],
        'navy' => [
            'body_bg'         => '#f2f5fa',
            'sidebar_dark_bg' => '#0f1e3d',
            'tint'            => '#e1e4e8',
            'accent'          => '#5b8cff',
        ],
        'emerald' => [
            'body_bg'    => '#f4faf7',
            'font_color' => '#16342a',
            'tint'       => '#e7f6ec',
            'accent'     => '#3ed04a',
        ],
        'amber' => [
            'body_bg'    => '#fbf5f0',
            'font_color' => '#3a2a1e',
            'tint'       => '#fdeee1',
            'accent'     => '#fba05c',
        ],
        'midnight' => [
            'body_bg'         => '#101214',
            'font_color'      => '#f6f6f6',
            'sidebar_dark_bg' => '#16181d',
            'tint'            => '#233e50',
        ],
    ];

    /**
     * @var string
     */
    protected $packagePath;

    /**
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->packagePath = realpath(__DIR__.'/../..');
        $this->files = $this->laravel['files'];

        $name = $this->argument('name');

        if ($name === static::ALL) {
            // 编译所有内置主题色
            return $this->compileAllColors();
        }

        $publish = $this->option('publish');
        $color = $this->getColor($name);

        $this->backupFiles();
        $this->replaceFiles($name, $color);

        try {
            $this->npmInstall();

            $this->info("[$name][$color] npm run production...");

            // 编译
            $this->runProcess("cd {$this->packagePath} && npm run prod", 1800);

            if ($publish) {
                $this->publishAssets();
            }
        } finally {
            // 重置文件
            $this->resetFiles();
        }
    }

    /**
     * 编译所有内置主题.
     */
    protected function compileAllColors()
    {
        foreach ($this->colors as $name => $_) {
            $this->call('admin:minify', ['name' => $name]);
        }
    }

    /**
     * 发布静态资源.
     */
    protected function publishAssets()
    {
        $options = ['--provider' => 'Dcat\Admin\AdminServiceProvider', '--force' => true, '--tag' => 'dcat-admin-assets'];

        $this->call('vendor:publish', $options);
    }

    /**
     * 替换文件.
     *
     * @param $name
     * @param $color
     */
    protected function replaceFiles($name, $color)
    {
        if ($name === static::DEFAULT) {
            return;
        }

        $mixFile = $this->getMixFile();
        $contents = str_replace('let theme = null', "let theme = '{$name}'", $this->files->get($mixFile));
        $this->files->put($mixFile, $contents);

        $colorFile = $this->getColorFile();
        $this->files->put($colorFile, "\$primary: $color;");

        $schemeFile = $this->getSchemeFile();
        $this->files->put($schemeFile, $this->buildSchemeContents($name));
    }

    /**
     * 生成主题方案变量文件内容.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildSchemeContents($name)
    {
        $scheme = $this->schemes[$name] ?? [];

        // 这些变量在 scss 里已有默认值：只有本主题真的提供了才写入，
        // 否则写成 null 会把默认值覆盖掉（$font-color 没有 !default，会导致文字色声明直接丢失）
        $optional = [
            'body_bg'         => '$body-bg',
            'font_color'      => '$font-color',
            'sidebar_dark_bg' => '$sidebar-dark-bg',
        ];

        // 这两个是主题方案新增的变量，必须始终声明，否则 `@if $scheme-tint` 会报未定义变量
        $required = [
            'tint'   => '$scheme-tint',
            'accent' => '$scheme-accent',
        ];

        $contents = [];

        foreach ($optional as $key => $variable) {
            if (isset($scheme[$key])) {
                $contents[] = sprintf('%s: %s;', $variable, $scheme[$key]);
            }
        }

        foreach ($required as $key => $variable) {
            $contents[] = sprintf('%s: %s;', $variable, $scheme[$key] ?? 'null');
        }

        return implode("\n", $contents)."\n";
    }

    /**
     * 备份文件.
     */
    protected function backupFiles()
    {
        if (! is_file($this->getMixBakFile())) {
            $this->files->copy($this->getMixFile(), $this->getMixBakFile());
        } else {
            $this->files->delete($this->getMixFile());
            $this->files->copy($this->getMixBakFile(), $this->getMixFile());
        }

        if (! is_file($this->getColorBakFile())) {
            $this->files->copy($this->getColorFile(), $this->getColorBakFile());
        }

        if (! is_file($this->getSchemeBakFile())) {
            $this->files->copy($this->getSchemeFile(), $this->getSchemeBakFile());
        }
    }

    /**
     * 重置文件.
     */
    protected function resetFiles()
    {
        $mixFile = $this->getMixFile();
        $mixBakFile = $this->getMixBakFile();

        if (is_file($mixBakFile)) {
            $this->files->delete($mixFile);
            $this->files->copy($mixBakFile, $mixFile);
            $this->files->delete($mixBakFile);
        }

        $colorFile = $this->getColorFile();
        $colorBakFile = $this->getColorBakFile();

        if (is_file($colorBakFile)) {
            $this->files->delete($colorFile);
            $this->files->copy($colorBakFile, $colorFile);
            $this->files->delete($colorBakFile);
        }

        $schemeFile = $this->getSchemeFile();
        $schemeBakFile = $this->getSchemeBakFile();

        if (is_file($schemeBakFile)) {
            $this->files->delete($schemeFile);
            $this->files->copy($schemeBakFile, $schemeFile);
            $this->files->delete($schemeBakFile);
        }
    }

    /**
     * @return string
     */
    protected function getMixFile()
    {
        return $this->packagePath.'/webpack.mix.js';
    }

    /**
     * @return mixed
     */
    protected function getMixBakFile()
    {
        return str_replace('.js', '.bak.js', $this->getMixFile());
    }

    /**
     * @return string
     */
    protected function getColorFile()
    {
        return $this->packagePath.'/resources/assets/dcat/sass/theme/_primary.scss';
    }

    /**
     * @return mixed
     */
    protected function getColorBakFile()
    {
        return str_replace('.scss', '.bak.scss', $this->getColorFile());
    }

    /**
     * @return mixed
     */
    protected function getSchemeFile()
    {
        return $this->packagePath.'/resources/assets/dcat/sass/theme/_scheme.scss';
    }

    /**
     * @return mixed
     */
    protected function getSchemeBakFile()
    {
        return str_replace('.scss', '.bak.scss', $this->getSchemeFile());
    }

    /**
     * 安装依赖.
     */
    protected function npmInstall()
    {
        if (is_dir($this->packagePath.'/node_modules')) {
            return;
        }

        $this->info('npm install...');

        $this->runProcess("cd {$this->packagePath} && npm install");
    }

    /**
     * 获取颜色.
     *
     * @param  string  $name
     * @return string
     */
    protected function getColor($name)
    {
        if ($name === static::DEFAULT) {
            return '';
        }

        INPUT_COLOR:

        $color = $this->option('color');

        if (! $color && isset($this->colors[$name])) {
            return $this->colors[$name];
        }

        if (! $color) {
            $color = $this->formatColor($this->ask('Please enter a color code(hex)'));
        }

        if (! $color) {
            goto INPUT_COLOR;
        }

        return $this->formatColor($color);
    }

    /**
     * @param  string  $color
     * @return string
     */
    protected function formatColor($color)
    {
        if ($color && ! Str::startsWith($color, '#')) {
            $color = "#$color";
        }

        return $color;
    }

    /**
     * 执行命令.
     *
     * @param  string  $command
     * @param  int  $timeout
     */
    protected function runProcess($command, $timeout = 1800)
    {
        $process = Helper::process($command, $timeout);

        $process->run(function ($type, $data) {
            if ($type === Process::ERR) {
                $this->warn($data);
            } else {
                $this->info($data);
            }
        });
    }
}
