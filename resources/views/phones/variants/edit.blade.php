@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Phones', 'url' => route('phones.index')], ['label' => $phone->name, 'url' => route('phones.edit', $phone->id)], ['label' => 'Edit Variant']],
            ])

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Edit Variant - {{ $phone->name }}</h5>

                            <form action="{{ route('phones.variants.update', [$phone->id, $variant->id]) }}" method="POST">
                                @csrf
                                @method('PUT')
                                @include('phones.variants._form', ['variant' => $variant, 'officialPrice' => $officialPrice, 'unofficialPrice' => $unofficialPrice, 'availability' => $availability])

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('phones.edit', $phone->id) }}" class="btn btn-light waves-effect">Cancel</a>
                                        <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-content-save-outline me-1"></i> Update</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                @if($variant->priceHistory()->exists())
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title mb-3">Price History</h6>
                                <ul class="list-unstyled mb-0">
                                    @foreach($variant->priceHistory()->latest('changed_at')->take(10)->get() as $entry)
                                        <li class="mb-2 pb-2 border-bottom">
                                            <span class="badge {{ $entry->price_type->badgeClass() }}">{{ $entry->price_type->label() }}</span>
                                            ৳{{ number_format($entry->amount) }}
                                            @if($entry->previous_amount)
                                                <small class="text-muted">(was ৳{{ number_format($entry->previous_amount) }})</small>
                                            @endif
                                            <br><small class="text-muted">{{ $entry->changed_at->format('d M Y, h:i A') }}</small>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

        </div>
    </div>
@endsection

@section('JScript')
    <script>$(document).ready(function () { $('.select2').select2({width: '100%', allowClear: true}); });</script>
@endsection
