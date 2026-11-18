@php($selectedValues = array_map('strval', Dcat\Admin\Support\Helper::array(old($column, $value))))
<div class="input-group input-group-sm quick-form-field">
    <select class="form-control {{$class}}" style="width: 100%;" name="{{$name}}[]" {!! $attributes !!} multiple data-placeholder="{{ $label }}">
        @foreach($options as $select => $option)
            <option value="{{$select}}" {{ in_array((string) $select, $selectedValues, true) ?'selected':'' }}>{{$option}}</option>
        @endforeach
    </select>
</div>

@include('admin::form.select-script', ['multiple' => true])
