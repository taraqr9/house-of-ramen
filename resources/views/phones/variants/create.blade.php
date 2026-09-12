@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Phones', 'url' => route('phones.index')], ['label' => $phone->name, 'url' => route('phones.edit', $phone->id)], ['label' => 'Add Variant']],
            ])

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title mb-3">Add Variant - {{ $phone->name }}</h5>

                            <form action="{{ route('phones.variants.store', $phone->id) }}" method="POST">
                                @csrf
                                @include('phones.variants._form', ['variant' => null, 'officialPrice' => null, 'unofficialPrice' => null, 'availability' => null])

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('phones.edit', $phone->id) }}" class="btn btn-light waves-effect">Cancel</a>
                                        <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-content-save-outline me-1"></i> Save</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@section('JScript')
    <script>$(document).ready(function () { $('.select2').select2({width: '100%', allowClear: true}); });</script>
@endsection
