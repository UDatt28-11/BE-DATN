<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}"> {{-- Quan trọng! --}}
    <title>Upload File to Google Drive</title>
</head>
<body>
    <h2>Upload a File to Google Drive</h2>

    <!-- Thông báo thành công -->
    @if (session('success'))
        <p style="color: green; padding: 10px; background: #d4edda; border-radius: 5px;">
            {{ session('success') }}
        </p>
    @endif

    <!-- Lỗi -->
    @if ($errors->any())
        <div style="color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin-bottom: 15px;">
            <ul style="margin: 0; padding-left: 20px;">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Form -->
    <form action="{{ route('upload.file') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <p>
            <input type="file" name="file_to_upload" required>
        </p>
        <button type="submit" style="padding: 8px 16px; background: #4285f4; color: white; border: none; border-radius: 4px; cursor: pointer;">
            Upload to Google Drive
        </button>
    </form>

    <hr>

    <h3>Test CSRF</h3>
    <form action="{{ route('test.csrf') }}" method="POST">
        @csrf
        <button type="submit">Test CSRF POST</button>
    </form>
    <p><a href="{{ route('test.csrf.get') }}">Xem CSRF Token (GET)</a></p>
</body>
</html>
