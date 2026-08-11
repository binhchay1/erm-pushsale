@extends('errors.layout')

@section('title', 'Không tìm thấy / Page not found')
@section('code', '404')
@section('accent_main', '#fb7185')
@section('accent_light', '#fecdd3')
@section('accent_soft', 'rgba(251,113,133,.12)')
@section('accent_chip', '#fda4af')
@section('vi_title', 'Trang bạn tìm đang bị thất lạc')
@section('en_title', 'The page you are looking for is missing')
@section('vi_desc', 'Đường dẫn có thể đã thay đổi, đã bị xóa hoặc hiện không còn khả dụng. Hãy kiểm tra lại URL hoặc quay về trang chính.')
@section('en_desc', 'The URL may have changed, been removed, or is no longer available. Please check the address or return to the main page.')
@section('vi_hint', 'Nếu bạn mở từ menu hệ thống, rất có thể route này đang trỏ sai hoặc chưa được cấu hình hoàn chỉnh.')
@section('en_hint', 'If you opened this from the system menu, the route may be incorrect or not fully configured yet.')

@section('actions_vi')
    <a href="{{ route('login') }}" class="btn btn-primary">Về đăng nhập</a>
    <button type="button" class="btn" onclick="history.back()">Quay lại</button>
@endsection

@section('actions_en')
    <a href="{{ route('login') }}" class="btn btn-primary">Back to login</a>
    <button type="button" class="btn" onclick="history.back()">Go back</button>
@endsection
