@extends('errors.layout')

@section('title', 'Cần đăng nhập / Sign in required')
@section('code', '401')
@section('accent_main', '#f59e0b')
@section('accent_light', '#fde68a')
@section('accent_soft', 'rgba(245,158,11,.12)')
@section('accent_chip', '#fbbf24')
@section('vi_title', 'Bạn cần đăng nhập lại')
@section('en_title', 'Please sign in again')
@section('vi_desc', 'Phiên làm việc đã hết hạn hoặc bạn chưa đăng nhập. Vui lòng đăng nhập lại để tiếp tục sử dụng hệ thống.')
@section('en_desc', 'Your session has expired or you are not signed in. Please sign in again to continue using the system.')
@section('vi_hint', 'Nếu bạn vừa thao tác xong rồi bị chuyển sang đây, rất có thể phiên đăng nhập đã hết hạn.')
@section('en_hint', 'If you were redirected here right after an action, your sign-in session likely expired.')

@section('actions_vi')
    <a href="{{ route('login') }}" class="btn btn-primary">Đăng nhập</a>
    <button type="button" class="btn" onclick="location.reload()">Tải lại</button>
@endsection

@section('actions_en')
    <a href="{{ route('login') }}" class="btn btn-primary">Sign in</a>
    <button type="button" class="btn" onclick="location.reload()">Reload</button>
@endsection
