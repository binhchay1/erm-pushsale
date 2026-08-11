@extends('errors.layout')

@section('title', 'Bảo trì hệ thống / Maintenance')
@section('code', '503')
@section('accent_main', '#60a5fa')
@section('accent_light', '#bfdbfe')
@section('accent_soft', 'rgba(96,165,250,.12)')
@section('accent_chip', '#93c5fd')
@section('vi_title', 'Hệ thống đang được bảo trì')
@section('en_title', 'The system is under maintenance')
@section('vi_desc', 'Chúng tôi đang nâng cấp hoặc bảo trì hệ thống trong thời gian ngắn. Vui lòng quay lại sau ít phút.')
@section('en_desc', 'We are upgrading or performing brief maintenance on the system. Please come back in a few minutes.')
@section('vi_hint', 'Nếu đây là thời điểm làm việc cao điểm, hãy liên hệ ban quản trị để kiểm tra lịch bảo trì.')
@section('en_hint', 'If this occurs during working hours, please contact the administrator to verify the maintenance window.')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Về trang chủ / Back to home</a>
    <button type="button" class="btn" onclick="location.reload()">Tải lại / Reload</button>
@endsection
