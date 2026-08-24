@extends('emails.layout')

@section('title', __('emails.test_subject'))

@section('content')
    <p style="margin:0 0 20px; color:#111827; font-size:16px; line-height:1.6;">
        {{ __('emails.test_body') }}
    </p>

    <p style="margin:0; color:#6b7280; font-size:13px; line-height:1.6;">
        {{ __('emails.test_sent_at', ['datetime' => now()->toDayDateTimeString()]) }}
    </p>
@endsection
