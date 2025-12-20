@php
    $appName = config('app.name', 'BookHomeStay');
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đặt lại mật khẩu của bạn - {{ $appName }}</title>
</head>
<body style="font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f7; margin: 0; padding: 40px 0;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
        <tr>
            <td align="center" style="background-color: #cb8670; padding: 25px;">
                <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">{{ $appName }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 40px; color: #333;">
                <h2 style="color: #cb8670; font-size: 22px; margin: 0 0 20px 0; font-weight: 600;">🔑 Đặt lại mật khẩu của bạn</h2>
                <p style="font-size: 16px; line-height: 1.6; margin: 0 0 15px 0;">Xin chào {{ $user->name ?? 'bạn' }},</p>
                <p style="font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn. Nhấn vào nút bên dưới để tạo mật khẩu mới:</p>

                <p style="text-align: center; margin: 40px 0;">
                    <a href="{{ $actionUrl  }}" style="background-color: #cb8670; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 2px 4px rgba(203, 134, 112, 0.3);">
                        Đặt lại mật khẩu
                    </a>
                </p>

                <p style="font-size: 14px; line-height: 1.6; color: #666; margin: 30px 0 10px 0;">Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.</p>
                <p style="font-size: 14px; line-height: 1.6; color: #666; margin: 0 0 0 0;">Liên kết sẽ hết hạn sau <strong>15 phút</strong> vì lý do bảo mật.</p>

                <p style="font-size: 16px; line-height: 1.6; margin: 40px 0 0 0;">Trân trọng,<br><strong style="color: #cb8670;">Đội ngũ {{ $appName }}</strong></p>
            </td>
        </tr>
        <tr>
            <td align="center" style="background-color: #f9fafb; color: #777; padding: 20px; font-size: 13px;">
                © {{ date('Y') }} {{ $appName }}. Mọi quyền được bảo lưu.
            </td>
        </tr>
    </table>
</body>
</html>
