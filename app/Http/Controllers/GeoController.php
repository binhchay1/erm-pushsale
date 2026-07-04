<?php

namespace App\Http\Controllers;

use App\Support\VietnamDivisions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API tra cứu địa giới hành chính cho các ô chọn (cascading, có tìm kiếm ở FE).
 *
 * mode = old  → Tỉnh → Quận/Huyện → Phường/Xã (đơn vị cũ).
 * mode = new  → Tỉnh → Phường/Xã (đơn vị 2 cấp từ 01/07/2025, bỏ Quận/Huyện).
 */
class GeoController extends Controller
{
    public function provinces(Request $request): JsonResponse
    {
        $mode = $request->query('mode', VietnamDivisions::MODE_OLD);

        return response()->json(
            $mode === VietnamDivisions::MODE_NEW
                ? VietnamDivisions::newProvinces()
                : VietnamDivisions::provinces()
        );
    }

    public function districts(string $province): JsonResponse
    {
        return response()->json(VietnamDivisions::districts($province));
    }

    public function wards(string $district): JsonResponse
    {
        return response()->json(VietnamDivisions::wards($district));
    }

    /** Phường/Xã trực thuộc Tỉnh/TP (đơn vị 2 cấp 2025). */
    public function provinceWards(string $province): JsonResponse
    {
        return response()->json(VietnamDivisions::newWards($province));
    }
}
