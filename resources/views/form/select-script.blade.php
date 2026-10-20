
@include('admin::scripts.select')

<script require="@select2?lang={{ config('app.locale') === 'en' ? '' : str_replace('_', '-', config('app.locale')) }}" init="{!! $selector !!}">
    var configs = {!! admin_javascript_json($configs) !!};

    @if(! empty($multiple))
    // 多选：有选中值时禁用占位的隐藏 input，避免额外提交空值。
    // 不能再单独用 Dcat.init 注册同一选择器，否则会被 initialized 标记跳过。
    $this.each(function () {
        var $select = $(this),
            $hidden = $select.siblings('input[type="hidden"][name="' + $select.attr('name') + '"]'),
            toggle = function () {
                var val = $select.val();

                $hidden.prop('disabled', !!(val && val.length > 0));
            };

        $select.on('change', toggle);
        toggle();
    });
    @endif

    @yield('admin.select-ajax')

    @if(isset($remoteOptions))
    $.ajax({!! admin_javascript_json($remoteOptions) !!}).done(function(data) {
        configs.data = data;

        $this.each(function (_, select) {
            select = $(select);

            select.select2(configs);

            var value = select.data('value') + '';

            if (value) {
                select.val(value.split(',')).trigger("change")
            }
        });
    });
    @else
    $this.select2(configs);
    @endif

    {!! $cascadeScript !!}
</script>

