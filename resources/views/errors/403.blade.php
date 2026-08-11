@extends('errors.layout')

@section('title', 'Không có quyền / Access denied')
@section('code', '403')
@section('accent_main', '#f97316')
@section('accent_light', '#fed7aa')
@section('accent_soft', 'rgba(249,115,22,.12)')
@section('accent_chip', '#fb923c')
@section('vi_title', 'Bạn không có quyền truy cập trang này')
@section('en_title', 'You do not have permission to access this page')
@section('vi_desc', 'Tài khoản hiện tại không được cấp quyền cho nội dung hoặc thao tác này. Vui lòng liên hệ quản trị viên nếu bạn nghĩ đây là nhầm lẫn.')
@section('en_desc', 'Your current account is not permitted to access this page or action. Please contact the administrator if you think this is a mistake.')
@section('vi_hint', 'Kiểm tra lại vai trò hoặc quyền của tài khoản trong hệ thống trước khi thao tác lại.')
@section('en_hint', 'Please review the role or permission assigned to your account before trying again.')

@section('actions_vi')
    <a href="{{ route('login') }}" class="btn btn-primary">Về đăng nhập</a>
    <button type="button" class="btn" onclick="history.back()">Quay lại</button>
@endsection

@section('actions_en')
    <a href="{{ route('login') }}" class="btn btn-primary">Back to login</a>
    <button type="button" class="btn" onclick="history.back()">Go back</button>
@endsection
