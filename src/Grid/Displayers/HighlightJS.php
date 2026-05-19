<?php

namespace Dcat\Admin\Grid\Displayers;

use Dcat\Admin\Admin;
use Dcat\Admin\Support\Helper;

/**
 * 代码块高亮显示
 */
class HighlightJS extends AbstractDisplayer
{
    protected static $js = [
        "https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/highlight.min.js",
        "https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@11.11.1/build/languages/json.min.js"
    ];

    protected static $css = [
        'https://cdn.jsdelivr.net/npm/highlight.js@11.7.0/styles/default.min.css',
    ];

    /**
     * 设置资源地址
     *
     * @param array|string|null $js
     * @param array|string|null $css
     *
     * @return void
     */
    public
    static function setResource(array|string|null $js = null, array|string|null $css = null): void
    {
        if ($js !== null) {
            static::$js = is_array($js) ? $js : [$js];
        }
        if ($css !== null) {
            static::$css = is_array($css) ? $css : [$css];
        }
    }

    /**
     * @param string $language 支持的语言请看：https://github.com/highlightjs/highlight.js/blob/main/SUPPORTED_LANGUAGES.md#supported-languages
     * @param array $styles
     * @param string $version
     *
     * @return array|mixed|string
     */
    public
    function display(string $language = 'json',
                     array  $styles = ['max-height: 180px', 'max-width: 300px'],
                     string $version = '11.11.1',
    ): mixed
    {
        static::setResource(js: [
            "https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@{$version}/build/highlight.min.js",
            "https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@{$version}/build/languages/{$language}.min.js"
        ],
            css: "https://cdn.jsdelivr.net/gh/highlightjs/cdn-release@{$version}/build/styles/default.min.css"
        );

        $this->addScript();

        if (is_array($this->value)) {
            $this->value = json_encode($this->value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $this->value = Helper::htmlEntityEncode($this->value);
        }

        $styles = join('; ', $styles);

        $html = <<<HTML
                <pre><code class="dump hljs language-{$language}" style="{$styles}">{$this->value}</code></pre>
                HTML;

        return $this->value === '' || $this->value === null ? $this->value : $html;
    }

    protected function addScript(): void
    {
        Admin::js(static::$js);
        Admin::css(static::$css);

        $script = <<<'JS'
            hljs.highlightAll();
            JS;

        Admin::script($script);

        $style = <<<'CSS'
            code.hljs * {
              font-family: "JetBrains Mono", monospace;
              font-optical-sizing: auto;
              font-style: normal;
              line-height: 1.2;
            }

            /* Webkit浏览器的滚动条样式 */
            .dump::-webkit-scrollbar {
              width: 2px;
              height: 2px;
            }

            .dump::-webkit-scrollbar-track {
              background: transparent;
            }

            .dump::-webkit-scrollbar-thumb {
              background: rgba(144, 147, 153, 0.3);
              border-radius: 0;
            }

            .dump::-webkit-scrollbar-thumb:hover {
              background: rgba(144, 147, 153, 0.5);
            }
            CSS;

        Admin::style($style);
    }
}
