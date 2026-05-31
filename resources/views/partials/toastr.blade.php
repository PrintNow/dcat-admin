@if($toastr = \Dcat\Admin\Support\SessionMessage::tryFrom(Session::get('dcat-admin-toastr')))
    <script>$(function () { toastr.{!! $toastr->getToastrType() !!}({!! json_encode($toastr->getMessage()) !!}, null, {!! admin_javascript_json($toastr->getOptions()) !!}); })</script>
@endif
