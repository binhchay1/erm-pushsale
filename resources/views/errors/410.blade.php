@extends('errors.layout')

@section('title', 'Chức năng đã được thay thế / Feature replaced')
@section('code', '410')
@section('accent_main', '#d946ef')
@section('accent_light', '#f5d0fe')
@section('accent_soft', 'rgba(217,70,239,.12)')
@section('accent_chip', '#e879f9')
@section('vi_title', 'Chức năng đã được thay thế')
@section('en_title', 'This feature has been replaced')
@section('vi_desc', 'Luồng hoặc chức năng này đã được thay thế bằng phiên bản mới. Vui lòng quay lại menu chính để chọn chức năng hiện hành.')
@section('en_desc', 'This workflow or feature has been replaced by a newer version. Please return to the menu and use the current feature.')
@section('vi_hint', 'Chức năng cũ đã bị tắt để tránh xử lý sai luồng. Hãy dùng chức năng mới trong menu hiện tại.')
@section('en_hint', 'The old feature was disabled to avoid processing the wrong workflow. Use the new feature in the current menu.')
@section('actions_vi')
    <a href="{{ route('login') }}" class="btn btn-primary">Về đăng nhập</a>
    <button type="button" class="btn" onclick="history.back()">Quay lại</button>
@endsection
@section('actions_en')
    <a href="{{ route('login') }}" class="btn btn-primary">Back to login</a>
    <button type="button" class="btn" onclick="history.back()">Go back</button>
@endsection
