<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    // Tắt tự động thêm created_at và updated_at vì bảng roles không có hai cột này
    public $timestamps = false;

    protected $fillable = ['name', 'description'];
}
