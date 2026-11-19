# skill-check

检查 `.agents/skills` 下 Markdown 中 PHP 代码块调用的 dcat-admin API 是否真实存在（只查方法存在性，不查参数）。

```bash
composer install
php tools/skill-check/check.php                    # 默认检查 .agents/skills，发现问题退出码为 1
php tools/skill-check/check.php --report docs/     # 只输出报告
php tools/skill-check/check.php --autoload=/path/to/vendor/autoload.php <path>
```

- 变量类型来自闭包参数类型、`use` 导入、常用变量名（`$grid`、`$form`、`$filter`、`$show` 等）和简单赋值；推断不出时跳过，宁可漏报也不误报。
- 演示自定义扩展（`Form::extend`、`Column::extend`、macro）的代码会被判定为不存在：在该行末尾加 `// skill-check:ignore`，或把代码块语言写成 ` ```php no-check`。
