@extends('errors.layout')

@section('title', 'Phiên hết hạn / Session expired')
@section('code', '419')
@section('accent_main', '#a855f7')
@section('accent_light', '#e9d5ff')
@section('accent_soft', 'rgba(168,85,247,.12)')
@section('accent_chip', '#c084fc')
@section('vi_title', 'Phiên bảo mật đã hết hạn')
@section('en_title', 'Your security session has expired')
@section('vi_desc', 'Trang đang mở quá lâu hoặc phiên bảo mật đã hết hạn. Vui lòng tải lại trang rồi thực hiện lại thao tác.')
@section('en_desc', 'The page stayed open too long or the security session expired. Please reload the page and try your action again.')
@section('vi_hint', 'Đây thường là lỗi tạm thời và có thể xử lý ngay bằng cách tải lại trang.')
@section('en_hint', 'This is usually temporary and can be resolved by reloading the page.')

@section('actions_vi')
    <a href="{{ route('login') }}" class="btn btn-primary">Đăng nhập</a>
    <button type="button" class="btn" onclick="location.reload()">Tải lại</button>
@endsection

@section('actions_en')
    <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
    <button type="button" class="btn" onclick="location.reload()">Reload</button>
@endsection
