@extends('layout.master', ['page_title' => $page_title])

@section('content')
    <div class="page-content">
        <div class="container-fluid">

            @include('partials.breadcrumb', [
                'items' => [['label' => 'Phones', 'url' => route('phones.index')], ['label' => 'Add']],
            ])

            <form action="{{ route('phones.store') }}" method="POST">
                @csrf

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-6"><h5 class="card-title mb-1">Add Phone</h5></div>
                                    <div class="col-sm-6">
                                        <div class="text-sm-end mt-3 mt-sm-0">
                                            <a href="{{ route('phones.index') }}" class="btn btn-secondary waves-effect waves-light"><i class="mdi mdi-arrow-left me-1"></i> Back</a>
                                        </div>
                                    </div>
                                </div>

                                <div class="alert alert-info">
                                    The phone image, RAM/storage variants, Bangladesh pricing, and availability are added after the phone is created.
                                </div>

                                @include('phones._form', ['spec' => null])

                                <div class="border-top pt-3 mt-2">
                                    <div class="d-flex justify-content-end gap-2">
                                        <a href="{{ route('phones.index') }}" class="btn btn-light waves-effect">Cancel</a>
                                        <button type="submit" class="btn btn-primary waves-effect waves-light"><i class="mdi mdi-content-save-outline me-1"></i> Save</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

        </div>
    </div>
@endsection

@section('JScript')
    <script>$(document).ready(function () { $('.select2').select2({width: '100%', allowClear: true}); });</script>
@endsection
