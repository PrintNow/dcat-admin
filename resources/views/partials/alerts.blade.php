@if($error = \Dcat\Admin\Support\SessionMessage::tryFrom(session()->get('error')))
    <div class="alert alert-danger alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-ban"></i> &nbsp;{{ $error->getTitle() }}</h4>
        <p>{!!  $error->getMessage() !!}</p>
    </div>
@elseif ($errors = session()->get('errors'))
    @if ($errors->hasBag('error'))
      <div class="alert alert-danger alert-dismissable">

        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        @foreach($errors->getBag("error")->toArray() as $message)
            <p>{!!  \Illuminate\Support\Arr::get($message, 0) !!}</p>
        @endforeach
      </div>
    @endif
@endif

@if($success = \Dcat\Admin\Support\SessionMessage::tryFrom(session()->get('success')))
    <div class="alert alert-success alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-check"></i> &nbsp;{{ $success->getTitle() }}</h4>
        <p>{!!  $success->getMessage() !!}</p>
    </div>
@endif

@if($info = \Dcat\Admin\Support\SessionMessage::tryFrom(session()->get('info')))
    <div class="alert alert-info alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-info"></i> &nbsp;{{ $info->getTitle() }}</h4>
        <p>{!!  $info->getMessage() !!}</p>
    </div>
@endif

@if($warning = \Dcat\Admin\Support\SessionMessage::tryFrom(session()->get('warning')))
    <div class="alert alert-warning alert-dismissable">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">×</button>
        <h4><i class="icon fa fa-warning"></i> &nbsp;{{ $warning->getTitle() }}</h4>
        <p>{!!  $warning->getMessage() !!}</p>
    </div>
@endif
