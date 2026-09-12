<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ config('app.name') }} Password Reset</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f6f8; font-family: Arial, Helvetica, sans-serif; color: #000000;">

<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f4f6f8; padding: 30px 0;">
    <tr>
        <td align="center">

            <table width="600" cellpadding="0" cellspacing="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; border: 1px solid #e5e7eb;">

                <tr>
                    <td style="background-color: #ffc107; padding: 22px 30px; color: #000000;">
                        <h2 style="margin: 0; font-size: 22px; font-weight: 700; color: #000000;">
                            Password Reset Request
                        </h2>
                        <p style="margin: 6px 0 0; font-size: 14px; color: #000000;">
                            {{ config('app.name') }} Account Security
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding: 30px; color: #000000;">
                        <p style="margin: 0 0 14px; font-size: 15px; color: #000000;">
                            Dear {{ $user->name }},
                        </p>

                        <p style="margin: 0 0 18px; font-size: 15px; line-height: 1.6; color: #000000;">
                            We received a request to reset your {{ config('app.name') }} account password. Click the button below to create a new password.
                        </p>

                        <p style="text-align: center; margin: 28px 0;">
                            <a href="{{ route('password.reset.show', ['token' => $plainToken, 'email' => $user->email]) }}"
                               style="display: inline-block; background-color: #ffc107; color: #000000; text-decoration: none; padding: 12px 26px; border-radius: 5px; font-size: 15px; font-weight: 700; border: 1px solid #eab308;">
                                Reset Password
                            </a>
                        </p>

                        <div style="background-color: #fffbea; border: 1px solid #facc15; border-radius: 6px; padding: 16px 18px; margin-bottom: 22px;">
                            <p style="margin: 0 0 8px; font-size: 14px; font-weight: 700; color: #000000;">
                                Important
                            </p>

                            <ul style="margin: 0; padding-left: 18px; font-size: 13px; line-height: 1.7; color: #000000;">
                                <li>This link will expire within 15 minutes.</li>
                                <li>This link will be invalid after successful password reset.</li>
                                <li>If you did not request this, please ignore this email.</li>
                            </ul>
                        </div>

                        <p style="margin: 0 0 12px; font-size: 14px; line-height: 1.6; color: #000000;">
                            If the button does not work, copy and paste the following link into your browser:
                        </p>

                        <p style="word-break: break-all; font-size: 12px; line-height: 1.5; color: #000000; margin: 0 0 22px;">
                            {{ route('password.reset.show', ['token' => $plainToken, 'email' => $user->email]) }}
                        </p>

                        <p style="margin: 0; font-size: 15px; color: #000000;">
                            Thank you,<br>
                            <strong>{{ config('app.name') }} Team</strong>
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="background-color: #fffbea; padding: 16px 30px; text-align: center; border-top: 1px solid #facc15;">
                        <p style="margin: 0; font-size: 12px; color: #000000;">
                            This is an automated email. Please do not reply to this message.
                        </p>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
