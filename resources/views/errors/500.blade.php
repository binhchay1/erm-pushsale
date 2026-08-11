@extends('errors.layout')

@section('title', 'Lỗi hệ thống / Server error')
@section('code', '500')
@section('accent_main', '#fb923c')
@section('accent_light', '#fdba74')
@section('accent_soft', 'rgba(251,146,60,.12)')
@section('accent_chip', '#fdba74')
@section('vi_title', 'Trang hiện đang gặp trục trặc')
@section('en_title', 'This page is currently having a problem')
@section('vi_desc', 'Hệ thống vừa gặp lỗi trong quá trình xử lý. Vui lòng thử tải lại trang. Nếu lỗi vẫn còn, hãy liên hệ ban quản trị để được hỗ trợ.')
@section('en_desc', 'The system encountered an issue while processing your request. Please reload the page. If the issue persists, contact the administrator for assistance.')
@section('vi_hint', 'Bạn không cần gửi mã log. Chỉ cần báo lại trang đang thao tác và thời điểm lỗi xảy ra cho ban quản trị.')
@section('en_hint', 'You do not need to send raw logs. Just report the page and the time of the issue to the administrator.')

@section('actions')
    <a href="{{ route('login') }}" class="btn btn-primary">Về đăng nhập / Back to login</a>
    <button type="button" class="btn" onclick="location.reload()">Tải lại / Reload</button>
@endsection
