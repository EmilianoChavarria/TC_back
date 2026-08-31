@extends('emails.layout')

@section('title', __('emails.welcome_title'))

@section('content')
    @php $brand = config('emails.brand'); @endphp

    <p style="margin:0 0 20px; color:#111827; font-size:16px; line-height:1.6;">
        {{ __('emails.greeting', ['name' => $fullName]) }}
    </p>

    <p style="margin:0 0 28px; color:#4b5563; font-size:15px; line-height:1.6;">
        {{ __('emails.welcome_intro') }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f3f4f6; border-radius:6px; margin:0 0 28px;">
        <tr>
            <td style="padding:24px;">
                <p style="margin:0; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; font-weight:600;">
                    {{ __('emails.email_label') }}
                </p>
                <p style="margin:6px 0 18px; color:#111827; font-size:16px;">{{ $email }}</p>

                <p style="margin:0; color:#6b7280; font-size:12px; text-transform:uppercase; letter-spacing:0.5px; font-weight:600;">
                    {{ __('emails.password_label') }}
                </p>
                <p style="margin:6px 0 0; color:#111827; font-size:16px; font-family:'Courier New',monospace;">{{ $password }}</p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 24px; color:#b45309; font-size:14px; line-height:1.6;">
        {{ __('emails.must_change_password') }}
    </p>

    <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <a href="{{ $loginUrl }}" style="display:inline-block; background-color:{{ $brand['primary'] }}; color:#ffffff; text-decoration:none; font-size:15px; font-weight:600; padding:14px 40px; border-radius:6px;">
                    {{ __('emails.login_button') }}
                </a>
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-top:12px;">
                <p style="margin:0; color:#6b7280; font-size:13px;">
                    {{ __('emails.login_url_label') }}
                    <a href="{{ $loginUrl }}" style="color:{{ $brand['primary_dark'] }}; text-decoration:none;">{{ $loginUrl }}</a>
                </p>
            </td>
        </tr>
    </table>
@endsection
