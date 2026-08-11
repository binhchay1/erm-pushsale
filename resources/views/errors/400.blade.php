@extends('errors.layout')

@section('title', 'Yêu cầu không hợp lệ / Invalid request')
@section('code', '400')
@section('accent_main', '#64748b')
@section('accent_light', '#cbd5e1')
@section('accent_soft', 'rgba(100,116,139,.12)')
@section('accent_chip', '#cbd5e1')
@section('vi_title', 'Yêu cầu không hợp lệ')
@section('en_title', 'Invalid request')
@section('vi_desc', 'Yêu cầu vừa gửi chưa đúng định dạng hoặc thiếu thông tin cần thiết. Vui lòng kiểm tra lại thao tác và thử lại.')
@section('en_desc', 'The request was missing required information or used an invalid format. Please review the action and try again.')
@section('vi_hint', 'Kiểm tra lại bộ lọc, dữ liệu vừa nhập hoặc liên kết đang mở.')
@section('en_hint', 'Review the filters, the data you entered, or the link you opened.')
@section('actions_vi')
    <a href="{{ route('login') }}" class="btn btn-primary">Về đăng nhập</a>
    <button type="button" class="btn" onclick="history.back()">Quay lại</button>
@endsection
@section('actions_en')
    <a href="{{ route('login') }}" class="btn btn-primary">Back to login</a>
    <button type="button" class="btn" onclick="history.back()">Go back</button>
@endsection
