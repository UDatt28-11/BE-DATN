<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File to Google Drive</title>
</head>
<body>
    <h2>Upload a File</h2>

    @if (session('success'))
        <p style="color: green;">{{ session('success') }}</p>
    @endif

    <form action="/upload-file" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="file_to_upload" required>
        <button type="submit">Upload File</button>
    </form>
</body>
</html>
