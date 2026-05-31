@if($toastr = \Dcat\Admin\Support\SessionMessage::tryFrom(Session::get('dcat-admin-toastr')))
    @php
        $type    = $toastr->getTitle();
        $message = $toastr->getMessage();
        $options = admin_javascript_json($toastr->getOptions());
    @endphp
    <script>$(function () { toastr.{{$type}}('{!!  $message  !!}', null, {!! $options !!}); })</script>
@endif
