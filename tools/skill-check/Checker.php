<?php

namespace SkillCheck;

use Dcat\Admin\Form;
use Dcat\Admin\Grid;
use Dcat\Admin\Show;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * 基于反射的轻量调用链检查：只判断"方法是否存在"，不检查参数。
 *
 * 类型推断来源：闭包参数类型、`use` 导入、常用变量名约定、简单赋值和方法返回类型（声明或 @return）。
 * 推断不出类型时停止检查该调用链，宁可漏报也不误报。
 */
class Checker
{
    /**
     * 常用变量名约定的默认类型。
     */
    protected const VARIABLES = [
        '$grid' => Grid::class,
        '$form' => Form::class,
        '$show' => Show::class,
        '$filter' => Grid\Filter::class,
        '$column' => Grid\Column::class,
        '$tree' => \Dcat\Admin\Tree::class,
        '$actions' => Grid\Displayers\Actions::class,
        '$batch' => Grid\Tools\BatchActions::class,
        '$tools' => Grid\Tools::class,
        '$create' => Grid\Tools\QuickCreate::class,
        '$selector' => Grid\Tools\Selector::class,
        '$footer' => Form\Footer::class,
        '$content' => \Dcat\Admin\Layout\Content::class,
        '$navbar' => \Dcat\Admin\Layout\Navbar::class,
        '$menu' => \Dcat\Admin\Layout\Menu::class,
    ];

    /**
     * 短类名在没有 use 导入时依次尝试的命名空间。
     */
    protected const NAMESPACES = [
        'Dcat\\Admin\\',
        'Dcat\\Admin\\Widgets\\',
        'Dcat\\Admin\\Layout\\',
        'Dcat\\Admin\\Http\\',
    ];

    /**
     * 通过 __call 动态创建表单字段的类。
     */
    protected const FIELD_CONTAINERS = [
        Form::class,
        \Dcat\Admin\Widgets\Form::class,
        Form\NestedForm::class,
        Form\EmbeddedForm::class,
        Form\BlockForm::class,
        Form\Row::class,
    ];

    protected array $fileImports = [];

    /**
     * @return string[]
     */
    public function checkMarkdown(string $file): array
    {
        $errors = [];
        $markdown = file_get_contents($file);

        preg_match_all('/^```php([^\n]*)\n(.*?)^```/ms', $markdown, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            if (str_contains($match[1][0], 'no-check')) {
                continue;
            }

            $startLine = substr_count($markdown, "\n", 0, $match[2][1]) + 1;

            foreach ($this->checkCode($match[2][0]) as [$line, $message]) {
                $errors[] = sprintf('%s:%d  %s', $file, $startLine + $line - 1, $message);
            }
        }

        return $errors;
    }

    /**
     * @return array<int, array{int, string}>
     */
    public function checkCode(string $code): array
    {
        $offset = 0;

        if (! str_starts_with(ltrim($code), '<?php')) {
            $code = "<?php\n".$code;
            $offset = 1;
        }

        $ignored = [];

        foreach (explode("\n", $code) as $i => $text) {
            if (str_contains($text, 'skill-check:ignore')) {
                $ignored[$i + 1] = true;
            }
        }

        $tokens = $this->tokenize($code);

        $imports = $this->parseImports($tokens);
        $scopes = $this->parseTypeHints($tokens, $imports);

        $errors = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $class = $this->chainStart($tokens, $i, $imports, $this->variablesAt($scopes, $i), $next, $static);

            if ($class === null) {
                continue;
            }

            [$type, $error] = $this->walkChain($tokens, $next, $class, $static);

            if ($error && ! isset($ignored[$error[0]])) {
                $errors[] = [$error[0] - $offset, $error[1]];
            }

            // 简单赋值：$x = $grid->column('a'); 之后到所在代码块结束，$x 视为 Column。
            if (
                $type
                && $i >= 2
                && $tokens[$i - 1] === '='
                && is_array($tokens[$i - 2])
                && $tokens[$i - 2][0] === T_VARIABLE
            ) {
                $scopes[] = [$tokens[$i - 2][1], $type, $i, $this->blockEnd($tokens, $i)];
            }
        }

        return $errors;
    }

    protected function chainStart(array $tokens, int $i, array $imports, array $variables, &$next, &$static): ?string
    {
        $token = $tokens[$i];
        $static = false;

        if (! is_array($token)) {
            return null;
        }

        // $this->response() 在 Action / Form 工具中返回 JsonResponse。
        if ($token[1] === '$this') {
            if ($this->isMethodCall($tokens, $i + 1, 'response')) {
                $next = $i + 4;

                while (($tokens[$next - 1] ?? null) !== ')') {
                    $next++;
                }

                return \Dcat\Admin\Http\JsonResponse::class;
            }

            return null;
        }

        if ($token[0] === T_VARIABLE) {
            // 跳过赋值目标和闭包参数声明本身。
            if (($tokens[$i + 1] ?? null) === '=' || ! $this->isObjectOperator($tokens[$i + 1] ?? null)) {
                return null;
            }

            $next = $i + 1;

            return $variables[$token[1]] ?? null;
        }

        if (
            in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
            && is_array($tokens[$i + 1] ?? null)
            && $tokens[$i + 1][0] === T_DOUBLE_COLON
            && ! (is_array($tokens[$i - 1] ?? null) && $tokens[$i - 1][0] === T_NEW)
        ) {
            $class = $this->resolveName($token[1], $imports);

            if ($class && str_starts_with($class, 'Dcat\\Admin\\')) {
                $next = $i + 1;
                $static = true;

                return $class;
            }
        }

        return null;
    }

    /**
     * @return array{?string, ?array{int, string}}
     */
    protected function walkChain(array $tokens, int $i, string $class, bool $static): array
    {
        $chain = (new ReflectionClass($class))->getShortName();

        while (true) {
            $operator = $tokens[$i] ?? null;

            if (! ($static ? (is_array($operator) && $operator[0] === T_DOUBLE_COLON) : $this->isObjectOperator($operator))) {
                return [$class, null];
            }

            $name = $tokens[$i + 1] ?? null;

            if (! is_array($name) || $name[0] !== T_STRING || ($tokens[$i + 2] ?? null) !== '(') {
                // 属性访问、常量或动态方法名，无法继续推断。
                return [null, null];
            }

            $method = $name[1];
            $chain .= ($static ? '::' : '->').$method.'()';

            [$found, $class] = $this->resolveCall($class, $method, $static);

            if (! $found) {
                return [null, [$name[2], "{$chain} 不存在"]];
            }

            if ($class === null) {
                return [null, null];
            }

            $i = $this->skipParentheses($tokens, $i + 2);
            $static = false;
        }
    }

    /**
     * @return array{bool, ?string} [方法是否存在, 返回值类型（未知时为 null）]
     */
    protected function resolveCall(string $class, string $method, bool $static): array
    {
        $reflection = new ReflectionClass($class);

        if ($reflection->hasMethod($method) && $reflection->getMethod($method)->isPublic()) {
            return [true, $this->returnType($reflection->getMethod($method), $class)];
        }

        if ($type = $this->docMethod($reflection, $method, $class)) {
            return [true, $type === '?' ? null : $type];
        }

        if ($static) {
            return [$reflection->hasMethod('__callStatic'), null];
        }

        if ($this->isA($class, Grid::class)) {
            return [true, Grid\Column::class];
        }

        if ($this->isA($class, Grid\Column::class)) {
            return [$this->hasDisplayer(Grid\Column::class, 'displayers', $method), Grid\Column::class];
        }

        if ($this->isA($class, Show::class)) {
            return [true, Show\Field::class];
        }

        if ($this->isA($class, Show\Field::class)) {
            return [$this->hasDisplayer(Show\Field::class, 'extendedFields', $method), Show\Field::class];
        }

        if ($this->isA($class, Grid\Filter::class)) {
            $filters = $this->staticProperty(Grid\Filter::class, 'supports') + $this->staticProperty(Grid\Filter::class, 'defaultFilters');

            return isset($filters[$method]) ? [true, $filters[$method]] : [false, null];
        }

        if ($this->isA($class, Grid\Filter\AbstractFilter::class)) {
            // __call 转发到当前 presenter，默认是 Text。
            return method_exists(Grid\Filter\Presenter\Text::class, $method)
                ? [true, Grid\Filter\Presenter\Text::class]
                : [false, null];
        }

        foreach (self::FIELD_CONTAINERS as $container) {
            if ($this->isA($class, $container)) {
                $field = $this->staticProperty(Form::class, 'fieldAlias')[$method] ?? $method;
                $field = $this->staticProperty(Form::class, 'availableFields')[$field] ?? null;

                if ($field) {
                    return [true, $field];
                }

                // BlockForm 找不到字段时转发给所属 Form。
                return $this->isA($class, Form\BlockForm::class)
                    ? $this->resolveCall(Form::class, $method, false)
                    : [false, null];
            }
        }

        if ($this->isA($class, \Dcat\Admin\Http\JsonResponse::class)) {
            // __call 只支持 xxxIf 条件后缀。
            $base = str_ends_with($method, 'If') ? substr($method, 0, -2) : null;

            return $base && $reflection->hasMethod($base) && $reflection->getMethod($base)->isPublic()
                ? [true, $class]
                : [false, null];
        }

        // 其他带 __call 的类无法确定，视为存在但停止推断。
        return [$reflection->hasMethod('__call'), null];
    }

    protected function hasDisplayer(string $class, string $property, string $method): bool
    {
        return isset($this->staticProperty($class, $property)[$method])
            || method_exists(Str::class, $method)
            || Str::hasMacro($method)
            || method_exists(Collection::class, $method);
    }

    protected function returnType(ReflectionMethod $method, string $class): ?string
    {
        $type = $method->getReturnType();

        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            if (in_array($name, ['static', 'self'], true)) {
                return $class;
            }

            if (! $type->isBuiltin() && class_exists($name)) {
                return $name;
            }

            return null;
        }

        if ($type !== null) {
            return null;
        }

        if (! preg_match('/@return\s+([^\s*]+)/', (string) $method->getDocComment(), $match)) {
            return null;
        }

        return $this->docType($match[1], $method->getDeclaringClass(), $class);
    }

    /**
     * 类 docblock 中的 @method 声明（含父类和 trait）。
     *
     * @return string|null 返回类型；存在但类型未知时为 '?'，不存在时为 null
     */
    protected function docMethod(ReflectionClass $reflection, string $method, string $class): ?string
    {
        for ($current = $reflection; $current; $current = $current->getParentClass()) {
            $docs = [$current];

            foreach ($current->getTraits() as $trait) {
                $docs[] = $trait;
            }

            foreach ($docs as $doc) {
                $pattern = '/@method\s+(?:static\s+)?(?:([^\s(]+)\s+)?'.preg_quote($method, '/').'\s*\(/';

                if (preg_match($pattern, (string) $doc->getDocComment(), $match)) {
                    return $this->docType($match[1] ?? '', $doc, $class) ?? '?';
                }
            }
        }

        return null;
    }

    protected function docType(string $types, ReflectionClass $declaring, string $class): ?string
    {
        foreach (explode('|', $types) as $type) {
            $type = ltrim(trim($type), '?');

            if (in_array($type, ['$this', 'static', 'self'], true)) {
                return $class;
            }

            if ($resolved = $this->resolveInFile($type, $declaring)) {
                return $resolved;
            }
        }

        return null;
    }

    protected function resolveInFile(string $name, ReflectionClass $declaring): ?string
    {
        if ($name === '' || ! preg_match('/^\\\\?[A-Z][\w\\\\]*$/', $name)) {
            return null;
        }

        if (str_starts_with($name, '\\')) {
            return class_exists($name) ? ltrim($name, '\\') : null;
        }

        $file = $declaring->getFileName();

        if (! isset($this->fileImports[$file])) {
            $this->fileImports[$file] = $file ? $this->parseImports($this->tokenize(file_get_contents($file))) : [];
        }

        $first = strstr($name, '\\', true) ?: $name;

        if (isset($this->fileImports[$file][$first])) {
            $candidate = $this->fileImports[$file][$first].substr($name, strlen($first));

            return class_exists($candidate) ? $candidate : null;
        }

        $candidate = $declaring->getNamespaceName().'\\'.$name;

        return class_exists($candidate) ? $candidate : null;
    }

    /**
     * @return array<string, string> 别名 => 完整类名
     */
    protected function parseImports(array $tokens): array
    {
        $imports = [];
        $depth = 0;

        foreach ($tokens as $i => $token) {
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }

            // 只处理顶层 use（闭包的 use (...) 和类内 use Trait 跳过）。
            if ($depth > 0 || ! is_array($token) || $token[0] !== T_USE) {
                continue;
            }

            $name = $tokens[$i + 1] ?? null;

            if (! is_array($name) || ! in_array($name[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                continue;
            }

            $full = ltrim($name[1], '\\');
            $alias = substr($full, (int) strrpos($full, '\\') + (str_contains($full, '\\') ? 1 : 0));

            if (is_array($tokens[$i + 2] ?? null) && $tokens[$i + 2][0] === T_AS) {
                $alias = $tokens[$i + 3][1] ?? $alias;
            }

            $imports[$alias] = $full;
        }

        return $imports;
    }

    /**
     * 闭包/函数参数的类型声明，如 function (Grid\Column $column)，作用域为该函数体。
     *
     * @return array<int, array{string, string, int, int}> [变量名, 类名, 起始 token, 结束 token]
     */
    protected function parseTypeHints(array $tokens, array $imports): array
    {
        $scopes = [];

        foreach ($tokens as $i => $token) {
            if (
                ! is_array($token)
                || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)
                || ! is_array($tokens[$i + 1] ?? null)
                || $tokens[$i + 1][0] !== T_VARIABLE
                || ! in_array($tokens[$i - 1] ?? null, ['(', ','], true)
            ) {
                continue;
            }

            if ($class = $this->resolveName($token[1], $imports)) {
                $scopes[] = [$tokens[$i + 1][1], $class, $i, $this->functionEnd($tokens, $i)];
            }
        }

        return $scopes;
    }

    /**
     * @return array<string, string>
     */
    protected function variablesAt(array $scopes, int $i): array
    {
        $variables = [];

        foreach (self::VARIABLES as $name => $class) {
            if (class_exists($class)) {
                $variables[$name] = [$class, -1];
            }
        }

        // 内层（起始位置更靠后）的作用域优先。
        foreach ($scopes as [$name, $class, $start, $end]) {
            if ($start <= $i && $i <= $end && $start > ($variables[$name][1] ?? -2)) {
                $variables[$name] = [$class, $start];
            }
        }

        return array_map(fn ($item) => $item[0], $variables);
    }

    /**
     * 参数所在函数的结束位置：普通函数到匹配的 }，箭头函数到语句结束。
     */
    protected function functionEnd(array $tokens, int $i): int
    {
        $count = count($tokens);
        $depth = 0;

        // 跳到参数列表结束的 )。
        for (; $i < $count; $i++) {
            if ($tokens[$i] === '(') {
                $depth++;
            } elseif ($tokens[$i] === ')' && $depth-- === 0) {
                break;
            }
        }

        for (; $i < $count; $i++) {
            if ($tokens[$i] === '{') {
                return $this->blockEnd($tokens, $i + 1);
            }

            if (is_array($tokens[$i]) && $tokens[$i][0] === T_DOUBLE_ARROW) {
                return $this->statementEnd($tokens, $i);
            }
        }

        return $count;
    }

    /**
     * 当前所在 { } 代码块的结束位置。
     */
    protected function blockEnd(array $tokens, int $i): int
    {
        $count = count($tokens);
        $depth = 0;

        for (; $i < $count; $i++) {
            if ($tokens[$i] === '{' || (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($tokens[$i] === '}' && $depth-- === 0) {
                return $i;
            }
        }

        return $count;
    }

    protected function statementEnd(array $tokens, int $i): int
    {
        $count = count($tokens);
        $depth = 0;

        for (; $i < $count; $i++) {
            if (in_array($tokens[$i], ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($tokens[$i], [')', ']', '}'], true)) {
                if ($depth-- === 0) {
                    return $i;
                }
            } elseif (($tokens[$i] === ';' || $tokens[$i] === ',') && $depth === 0) {
                return $i;
            }
        }

        return $count;
    }

    protected function resolveName(string $name, array $imports): ?string
    {
        if (str_starts_with($name, '\\')) {
            return class_exists($name) ? ltrim($name, '\\') : null;
        }

        $first = strstr($name, '\\', true) ?: $name;

        if (isset($imports[$first])) {
            $candidate = $imports[$first].substr($name, strlen($first));

            return class_exists($candidate) ? $candidate : null;
        }

        foreach (self::NAMESPACES as $namespace) {
            if (class_exists($namespace.$name)) {
                return $namespace.$name;
            }
        }

        return null;
    }

    protected function staticProperty(string $class, string $property): array
    {
        $reflection = new ReflectionClass($class);

        return $reflection->hasProperty($property) ? (array) $reflection->getStaticPropertyValue($property) : [];
    }

    protected function isA(string $class, string $parent): bool
    {
        return class_exists($parent) && is_a($class, $parent, true);
    }

    protected function isObjectOperator($token): bool
    {
        return is_array($token) && in_array($token[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR], true);
    }

    protected function isMethodCall(array $tokens, int $i, string $method): bool
    {
        return $this->isObjectOperator($tokens[$i] ?? null)
            && is_array($tokens[$i + 1] ?? null)
            && $tokens[$i + 1][1] === $method
            && ($tokens[$i + 2] ?? null) === '(';
    }

    protected function skipParentheses(array $tokens, int $i): int
    {
        $depth = 0;
        $count = count($tokens);

        for (; $i < $count; $i++) {
            if ($tokens[$i] === '(') {
                $depth++;
            } elseif ($tokens[$i] === ')' && --$depth === 0) {
                return $i + 1;
            } elseif (is_array($tokens[$i]) && in_array($tokens[$i][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
                // 字符串插值中的 { 与 } 成对出现，不影响括号深度。
                continue;
            }
        }

        return $count;
    }

    protected function tokenize(string $code): array
    {
        return array_values(array_filter(
            token_get_all($code),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
        ));
    }
}
