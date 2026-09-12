<div class="row">
    <div class="col-12">
        <div class="page-title-box d-sm-flex align-items-center justify-content-between">
            <div class="page-title-right">
                <ol class="breadcrumb m-0">
                    @foreach($items ?? [] as $item)
                        @if(!empty($item['url']))
                            <li class="breadcrumb-item">
                                <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                            </li>
                        @else
                            <li class="breadcrumb-item active">
                                {{ $item['label'] }}
                            </li>
                        @endif
                    @endforeach
                </ol>
            </div>

            <h4 class="mb-sm-0 font-size-18">
                {{ $title ?? '' }}
            </h4>
        </div>
    </div>
</div>
