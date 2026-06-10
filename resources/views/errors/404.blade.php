@extends('errors.layout')

@section('title', 'Không tìm thấy')
@section('code', '404')

@section('content')
    <p class="status">Mã lỗi 404</p>
    <h1>Không tìm thấy trang</h1>
    <p class="desc">Đường dẫn không tồn tại hoặc đã bị xóa. Kiểm tra lại URL hoặc quay về trang chủ.</p>
    <div class="actions">
        <a href="{{ route('login') }}" class="btn-primary">Về đăng nhập</a>
        <button type="button" class="btn-outline" onclick="history.back()">Quay lại</button>
    </div>
@endsection
