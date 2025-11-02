<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory; // Thêm dòng này
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory; // Và thêm dòng này

    // Cho phép điền dữ liệu hàng loạt vào cột 'name' và 'description'
    protected $fillable = ['name', 'description'];
}
