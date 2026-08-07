@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;font-weight:700;color:#111827;">{{ __('emails.receipt_hello') }}</p>

    <p style="margin:0 0 20px;font-size:14px;color:#4b5563;line-height:1.7;">
        {{ __('emails.receipt_intro') }}
        <strong style="color:#1e3a8a;">{{ $request->ticketLabel }}</strong>
        {{ __('emails.receipt_intro_from', ['name' => $request->full_name]) }}
    </p>

    <p style="margin:0 0 8px;font-size:14px;color:#4b5563;line-height:1.7;">{{ __('emails.receipt_attached') }}</p>

    <p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">{{ __('emails.receipt_note') }}</p>
@endsection
