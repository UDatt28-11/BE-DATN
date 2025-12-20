<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận tài khoản của bạn - BookHomeStay</title>
</head>
<body style="font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background-color: #f4f4f7; margin: 0; padding: 40px 0;">
    <table align="center" width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.05);">
        <tr>
            <td align="center" style="background-color: #cb8670; padding: 25px;">
                <h1 style="color: #ffffff; margin: 0; font-size: 28px; font-weight: 600;">BookHomeStay</h1>
            </td>
        </tr>
        <tr>
            <td style="padding: 40px; color: #333;">
                <p style="font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">Xin chào bạn,</p>
                <p style="font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;">Cảm ơn bạn đã đăng ký tài khoản tại <strong>BookHomeStay</strong>.</p>
                <p style="font-size: 16px; line-height: 1.6; margin: 0 0 30px 0;">Vui lòng nhấn vào nút bên dưới để xác minh địa chỉ email của bạn:</p>

                <p style="text-align: center; margin: 40px 0;">
                    <a href="{{ $url }}" style="background-color: #cb8670; color: #ffffff; padding: 14px 32px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 2px 4px rgba(203, 134, 112, 0.3);">
                        Xác minh email
                    </a>
                </p>

                <p style="font-size: 14px; line-height: 1.6; color: #666; margin: 30px 0 0 0;">Nếu bạn không tạo tài khoản này, hãy bỏ qua email này.</p>

                <p style="font-size: 16px; line-height: 1.6; margin: 40px 0 0 0;">Trân trọng,<br><strong style="color: #cb8670;">Đội ngũ BookHomeStay</strong></p>
            </td>
        </tr>
        <tr>
            <td align="center" style="background-color: #f9fafb; color: #777; padding: 20px; font-size: 13px;">
                © {{ date('Y') }} BookHomeStay. Mọi quyền được bảo lưu.
            </td>
        </tr>
    </table>
</body>
</html>
