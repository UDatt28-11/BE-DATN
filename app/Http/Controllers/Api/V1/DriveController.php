<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomImage;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DriveController extends Controller
{
    public function uploadRoomImage(Request $request, GoogleDriveService $drive)
    {
        $validator = Validator::make($request->all(), [
            'room_id' => ['required', 'exists:rooms,id'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'is_primary' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $room = Room::findOrFail($request->integer('room_id'));

        $file = $request->file('image');
        $uploaded = $drive->upload($file->getRealPath(), $file->getClientOriginalName());

        $image = RoomImage::create([
            'room_id' => $room->id,
            'drive_file_id' => $uploaded->getId(),
            'web_view_link' => $uploaded->getWebViewLink(),
            'mime_type' => $uploaded->getMimeType(),
            'size_bytes' => $uploaded->getSize() ? (int) $uploaded->getSize() : null,
            'image_url' => $uploaded->getWebViewLink(),
            'is_primary' => (bool) $request->boolean('is_primary', false),
        ]);

        return response()->json([
            'success' => true,
            'image' => $image,
        ], 201);
    }

    public function listRoomImages(Room $room)
    {
        return response()->json([
            'data' => $room->images()->orderByDesc('is_primary')->latest()->get(),
        ]);
    }

    public function deleteRoomImage(RoomImage $image, GoogleDriveService $drive)
    {
        // Xoá trên Drive trước (idempotent), sau đó xoá DB
        $drive->deleteFile($image->drive_file_id);
        $image->delete();

        return response()->json(['success' => true]);
    }

    public function setPrimary(RoomImage $image)
    {
        DB::transaction(function () use ($image) {
            // Unset primary cho các ảnh khác cùng phòng
            RoomImage::where('room_id', $image->room_id)
                ->where('id', '!=', $image->id)
                ->update(['is_primary' => false]);

            $image->update(['is_primary' => true]);
        });

        return response()->json([
            'success' => true,
            'image' => $image->fresh(),
        ]);
    }
}


