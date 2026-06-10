@extends('errors.layout')

@section('title', 'Lỗi hệ thống')
@section('code', '500')

@section('content')
    <p class="status">Mã lỗi 500</p>
    <h1>Lỗi hệ thống</h1>
    <p class="desc">Đã xảy ra sự cố phía máy chủ. Vui lòng thử tải lại hoặc quay về trang chủ.</p>
    <div class="actions">
        <a href="{{ route('login') }}" class="btn-primary">Về đăng nhập</a>
        <button type="button" class="btn-outline" onclick="location.reload()">Tải lại</button>
    </div>
@endsection
