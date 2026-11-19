<?php

namespace Dcat\Admin\Form\Field;

use Dcat\Admin\Admin;
use Dcat\Admin\Form\Field;
use Illuminate\Support\Str;

class Text extends Field
{
    use PlainInput;
    use Sizeable;

    /**
     * 是否在粘贴时将全角字符转为半角后再交给 inputmask 处理.
     *
     * @var bool
     */
    protected $inputmaskNormalizePaste = false;

    /**
     * 已有值不符合掩码时是否保留原值（不挂载掩码）.
     *
     * @var bool
     */
    protected $inputmaskKeepUnfitValue = false;

    public function __construct($column, $arguments = [])
    {
        if (static::class === self::class) {
            $this->prepend('<i class="feather icon-edit-2"></i>');
        }

        parent::__construct($column, $arguments);
    }

    /**
     * Render this filed.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function render()
    {
        $this->initPlainInput();
        $this->initSize();

        $this->defaultAttribute('type', 'text')
            ->defaultAttribute('name', $this->getElementName())
            ->defaultAttribute('value', $this->value())
            ->defaultAttribute('class', 'form-control '.$this->getElementClassString())
            ->defaultAttribute('placeholder', $this->placeholder());

        $this->addVariables([
            'prepend' => $this->prepend,
            'append'  => $this->append,
        ]);

        return parent::render();
    }

    /**
     * Set input type.
     *
     * @param  string  $type
     * @return $this
     */
    public function type(string $type)
    {
        return $this->attribute('type', $type);
    }

    /**
     * Set "data-match" attribute.
     *
     * @see http://1000hz.github.io/bootstrap-validator/
     *
     * @param  string|Field  $field
     * @param  string  $error
     * @return $this
     */
    public function same($field, ?string $error = null)
    {
        $field = $field instanceof Field ? $field : $this->form->field($field);
        $name = $field->column();

        if ($name.'_confirmation' === $this->column) {
            $field->rules('confirmed');
        } else {
            $this->rules('nullable|same:'.$name);
        }

        $attributes = [
            'data-match'       => $field->getElementClassSelector(),
            'data-match-error' => str_replace(
                [':attribute', ':other'],
                [$field->label(), $this->label()],
                $error ?: trans('admin.validation.match')
            ),
        ];

        return $this->attribute($attributes);
    }

    /**
     * @param  int  $length
     * @param  string|null  $error
     * @return $this
     */
    public function minLength(int $length, ?string $error = null)
    {
        $this->rules('nullable|min:'.$length);

        return $this->attribute([
            'data-minlength'       => $length,
            'data-minlength-error' => str_replace(
                [':attribute', ':min'],
                [$this->label, $length],
                $error ?: trans('admin.validation.minlength')
            ),
        ]);
    }

    /**
     * @param  int  $length
     * @param  string|null  $error
     * @return $this
     */
    public function maxLength(int $length, ?string $error = null)
    {
        Admin::script(
            <<<'JS'
Dcat.validator.extend('maxlength', function ($el) {
    return $el.val().length > $el.attr('data-maxlength');
});
JS
        );

        $this->rules('max:'.$length);

        return $this->attribute([
            'data-maxlength'       => $length,
            'data-maxlength-error' => str_replace(
                [':attribute', ':max'],
                [$this->label, $length],
                $error ?: trans('admin.validation.maxlength')
            ),
        ]);
    }

    /**
     * Add inputmask to an elements.
     *
     * @param  array  $options
     * @return $this
     */
    public function inputmask($options)
    {
        Admin::js('@jquery.inputmask');

        $options = admin_javascript_json($options);

        if (! $this->inputmaskNormalizePaste && ! $this->inputmaskKeepUnfitValue) {
            $this->script = "Dcat.init('{$this->getElementClassSelector()}', function (self) {
            self.inputmask($options);
        });";

            return $this;
        }

        if ($this->inputmaskNormalizePaste) {
            $options = '$.extend({onBeforePaste: '.static::inputmaskPasteNormalizer().'}, '.$options.')';
        }

        $script = "self.inputmask($options);";

        if ($this->inputmaskKeepUnfitValue) {
            // 已有值（如编辑页中的历史数据）无法被掩码完整表示时（val 与去掉掩码后的值都与原值不一致），
            // 不挂载掩码并恢复原值，避免显示被截断、保存时被静默覆盖；符合掩码的值行为不变.
            $script = <<<JS
var original = self.val(), trimmed = $.trim(original);
            {$script}
            if (trimmed !== '' && self.val() !== trimmed && self.inputmask('unmaskedvalue') !== trimmed) {
                self.inputmask('remove');
                self.val(original);
            }
JS;
        }

        $this->script = "Dcat.init('{$this->getElementClassSelector()}', function (self) {
            {$script}
        });";

        return $this;
    }

    /**
     * 粘贴时先将全角字符（如全角数字、全角标点、全角空格）转为半角，再交给 Inputmask 默认的粘贴处理逻辑.
     *
     * 只转换全角字符，其余输入原样保留；Inputmask 默认的 onBeforePaste 即调用 onBeforeMask，
     * 这里保持同样的后续处理，因此不含全角字符的粘贴结果与之前完全一致.
     *
     * @internal
     *
     * @return string
     */
    public static function inputmaskPasteNormalizer()
    {
        return <<<'JS'
function (value, opts) {
    value = String(value).replace(/[\uFF01-\uFF5E]/g, function (c) {
        return String.fromCharCode(c.charCodeAt(0) - 0xFEE0);
    }).replace(/\u3000/g, ' ');

    return $.isFunction(opts.onBeforeMask) ? opts.onBeforeMask(value, opts) : value;
}
JS;
    }

    /**
     * @param  array  $options
     * @return array
     */
    protected function formatOptions($options)
    {
        $original = [];
        $toReplace = [];

        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $subArray = $this->formatOptions($value);
                $options[$key] = $subArray['options'];
                
                // 使用array_push和展开运算符，避免重复的array_merge
                array_push($original, ...$subArray['original']);
                array_push($toReplace, ...$subArray['toReplace']);
            } elseif (str_contains($value, 'function')) {
                $original[] = $value;
                $options[$key] = "%{$key}%";
                $toReplace[] = "\"%{$key}%\"";
            }
        }

        return compact('original', 'toReplace', 'options');
    }

    /**
     * Add datalist element to Text input.
     *
     * @param  array  $entries
     * @return $this
     */
    public function datalist($entries = [])
    {
        $id = Str::random(8);

        $this->defaultAttribute('list', "list-{$id}");

        $datalist = "<datalist id=\"list-{$id}\">";
        foreach ($entries as $k => $v) {
            $value = is_string($k) ? "value=\"{$k}\"" : '';

            $datalist .= "<option {$value}>{$v}</option>";
        }
        $datalist .= '</datalist>';

        Admin::script("$('#list-{$id}').parent().hide()");

        return $this->append($datalist);
    }
}
