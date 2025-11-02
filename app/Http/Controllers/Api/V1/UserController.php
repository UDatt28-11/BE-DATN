<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // ... (các method khác như index, store... sẽ làm ở Epic 1) ...

    public function lookup(Request $request) {
        $owners = User::whereHas('roles', fn($q) => $q->where('name', 'owner'))
            ->orWhereHas('roles', fn($q) => $q->where('name', 'super_admin'))
            ->select('id', 'full_name') // Lấy 'full_name'
            ->get();
        return response()->json(['data' => $owners]);
    }
}
