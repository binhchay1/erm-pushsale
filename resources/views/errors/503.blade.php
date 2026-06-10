@extends('errors.layout')

@section('title', 'Bảo trì hệ thống')
@section('code', '503')

@section('content')
    <p class="status">Mã lỗi 503</p>
    <h1>Hệ thống đang bảo trì</h1>
    <p class="desc">SaleOps đang được nâng cấp hoặc bảo trì ngắn. Vui lòng quay lại sau vài phút.</p>
    <div class="actions">
        <a href="{{ url('/') }}" class="btn-primary">Về trang chủ</a>
        <button type="button" class="btn-outline" onclick="location.reload()">Tải lại</button>
    </div>
@endsection
