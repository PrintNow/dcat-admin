@if(Session::has('dcat-admin-toastr'))
    @php
        $toastr  = Session::get('dcat-admin-toastr');
        $type    = $toastr->getTitle();
        $message = $toastr->getMessage();
        $options = admin_javascript_json($toastr->getOptions());
    @endphp
    <script>$(function () { toastr.{{$type}}('{!!  $message  !!}', null, {!! $options !!}); })</script>
@endif