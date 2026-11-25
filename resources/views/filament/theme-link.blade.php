@php($themeFile = public_path('css/abu-admin.css'))
<meta name="theme-color" content="#041433">
<link rel="stylesheet" href="{{ asset('css/abu-admin.css') }}@if(is_file($themeFile))?v={{ filemtime($themeFile) }}@endif">
