@php
    $appName = config('app.name', 'BookStay');
@endphp

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đặt lại mật khẩu - {{ $appName }}</title>
</head>
<body style="font-family: 'Segoe UI', Roboto, sans-serif; background-color: #f4f4f7; margin: 0; padding: 40px 0;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
        <tr>
            <td align="center" style="background-color: #2563eb; padding: 25px;">
                <h1 style="color: #ffffff; margin: 0;">{{ $appName }}</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 40px; color: #333;">
                <h2 style="color: #2563eb;">🔑 Đặt lại mật khẩu của bạn</h2>
                <p>Xin chào {{ $user->name ?? 'bạn' }},</p>
                <p>Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.  
                Nhấn vào nút bên dưới để tạo mật khẩu mới:</p>

                <p style="text-align: center; margin: 40px 0;">
                    <a href="{{ $url }}" style="background-color: #2563eb; color: #fff; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;">
                        Đặt lại mật khẩu
                    </a>
                </p>

                <p>Nếu bạn không thực hiện yêu cầu này, vui lòng bỏ qua email này.</p>
                <p>Liên kết sẽ hết hạn sau <strong>60 phút</strong> vì lý do bảo mật.</p>

                <p>Trân trọng,<br><strong>Đội ngũ {{ $appName }} 💙</strong></p>
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
