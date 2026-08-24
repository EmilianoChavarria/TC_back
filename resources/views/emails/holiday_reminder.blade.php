@extends('emails.layout')

@section('title', __('emails.holiday_reminder_title', ['year' => $year]))

@section('content')
    <p style="margin:0 0 20px; color:#111827; font-size:16px; line-height:1.6;">
        {{ __('emails.holiday_reminder_heading', ['year' => $year]) }}
    </p>

    <p style="margin:0 0 24px; color:#4b5563; font-size:15px; line-height:1.6;">
        {{ __('emails.holiday_reminder_body', ['year' => $year]) }}
    </p>

    <p style="margin:0 0 28px; color:#b45309; font-size:14px; line-height:1.6;">
        {{ __('emails.holiday_reminder_notice') }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <a href="{{ $portalUrl }}" style="display:inline-block; background-color:#2563eb; color:#ffffff; text-decoration:none; font-size:15px; font-weight:600; padding:14px 40px; border-radius:6px;">
                    {{ __('emails.holiday_reminder_button') }}
                </a>
            </td>
        </tr>
    </table>
@endsection
