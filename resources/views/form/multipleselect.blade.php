<div class="{{$viewClass['form-group']}}">

    <div  class="{{$viewClass['label']}} control-label">
        <span>{!! $label !!}</span>
    </div>

    <div class="{{$viewClass['field']}}">

        @include('admin::form.error')

        <select class="form-control {{$class}}" style="width: 100%!important;" name="{{$name}}[]" multiple="multiple" data-placeholder="{{ $placeholder }}" {!! $attributes !!} >
            @foreach($options as $select => $option)
                <option value="{{ $select }}" {{  in_array($select, (array) $value) ?'selected':'' }}>{{$option}}</option>
            @endforeach
        </select>
        <input type="hidden" name="{{$name}}[]" {!! $value ? 'disabled' : '' !!}/>

        @include('admin::form.help-block')

    </div>
</div>

@include('admin::form.select-script')

<script>
$('{{$selector}}').off('change.select2').on('change.select2', function () {
    $(this).closest('.field').find('input[type="hidden"][name="{{$name}}[]"]').prop('disabled', $(this).val()?.length > 0);
});
</script>
