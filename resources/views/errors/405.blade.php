@extends('errors.layout')

@section('title', 'Phương thức không được hỗ trợ / Method not allowed')
@section('code', '405')
@section('accent_main', '#06b6d4')
@section('accent_light', '#bae6fd')
@section('accent_soft', 'rgba(6,182,212,.12)')
@section('accent_chip', '#67e8f9')
@section('vi_title', 'Phương thức không được hỗ trợ')
@section('en_title', 'Method not allowed')
@section('vi_desc', 'Trang này không hỗ trợ phương thức truy cập vừa dùng. Vui lòng quay lại màn hình trước đó và thao tác lại.')
@section('en_desc', 'This page does not support the request method that was used. Please go back and try the action again.')
@section('vi_hint', 'Không dùng lại liên kết gửi form cũ. Hãy mở lại trang từ menu hoặc nút thao tác chính.')
@section('en_hint', 'Do not reuse an old form submission link. Open the page again from the menu or main action button.')
@section('actions_vi')
    <a href="{{ route('login') }}" class="btn btn-primary">Về đăng nhập</a>
    <button type="button" class="btn" onclick="history.back()">Quay lại</button>
@endsection
@section('actions_en')
    <a href="{{ route('login') }}" class="btn btn-primary">Back to login</a>
    <button type="button" class="btn" onclick="history.back()">Go back</button>
@endsection
