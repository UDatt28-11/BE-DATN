<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\SupplyLogController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/upload-form', function () {
    return view('upload');
});

Route::post('/upload-file', function (Request $request) {
    // Kiểm tra xem có file được gửi lên không
    if (!$request->hasFile('file_to_upload')) {
        return 'Please select a file to upload.';
    }

    $file = $request->file('file_to_upload');
    $fileName = time() . '_' . $file->getClientOriginalName();

    // Dòng lệnh "thần thánh": Lưu file lên Google Drive
    Storage::disk('google')->put($fileName, file_get_contents($file));

    // Quay lại trang upload với thông báo thành công
    return back()->with('success', 'File was uploaded to Google Drive successfully!');
});

