<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Xác nhận tài khoản - BookHomeStay</title>
</head>
<body>
    <h2>Xin chào {{ $user->name ?? 'bạn' }},</h2>
    <p>Cảm ơn bạn đã đăng ký tài khoản tại <strong>BookHomeStay</strong>.</p>
    <p>Vui lòng nhấn vào nút bên dưới để xác minh địa chỉ email của bạn:</p>

    <p style="margin: 30px 0;">
        <a href="{{ $url }}" style="background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px;">
            Xác minh email
        </a>
    </p>

    <p>Nếu bạn không tạo tài khoản này, hãy bỏ qua email này.</p>
    <hr>
    <p>Trân trọng,<br>Đội ngũ BookHomeStay</p>
</body>
</html>
