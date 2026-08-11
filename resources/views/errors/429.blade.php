@extends('errors.layout')

@section('title', 'Quá nhiều yêu cầu / Too many requests')
@section('code', '429')
@section('accent_main', '#06b6d4')
@section('accent_light', '#bae6fd')
@section('accent_soft', 'rgba(6,182,212,.12)')
@section('accent_chip', '#67e8f9')
@section('vi_title', 'Bạn đang thao tác quá nhanh')
@section('en_title', 'You are sending requests too quickly')
@section('vi_desc', 'Hệ thống đang tạm giới hạn số lần thử để bảo vệ dịch vụ. Vui lòng đợi một lúc rồi thử lại.')
@section('en_desc', 'The system is temporarily rate limiting requests to protect the service. Please wait a moment and try again.')
@section('vi_hint', 'Nếu bạn đang import hoặc đồng bộ dữ liệu, hãy thử lại sau ít phút.')
@section('en_hint', 'If you are importing or syncing data, please retry after a short wait.')

@section('actions')
    <a href="{{ url('/') }}" class="btn btn-primary">Về trang chủ / Back to home</a>
    <button type="button" class="btn" onclick="location.reload()">Thử lại / Retry</button>
@endsection
