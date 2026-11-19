@extends('admin::grid.displayer.editinline.template')

@section('field')
    <input class="form-control ie-input"/>
@endsection

<script>
@section('popover-content')
    $template.find('input').attr('value', $trigger.data('value'));
@endsection

@section('popover-shown')
    @if(! empty($mask))
    $popover.find('.ie-input').inputmask({!! admin_javascript_json($mask) !!});
    @if(! empty($unmask))
    $popover.find('.ie-input').attr('data-unmask', 1);
    @endif
    @endif
@endsection
</script>
