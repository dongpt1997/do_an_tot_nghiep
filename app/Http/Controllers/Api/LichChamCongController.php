<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LichChamCong;
use App\Models\TongThuNhap;
use App\Models\User;
use App\Models\userInfo;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LichChamCongController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */

    public function addSave(Request $request)
    {
        try {
            DB::beginTransaction();
            $today = Carbon::now()->format('Y-m-d');
            $checkQR = User::select(
                DB::raw('user_info.ma_QR as ma_QR,users.id as userId')
            )
                ->join('user_info', 'user_info.user_id', '=', 'users.id')
                ->Where('ma_QR', $request->ma_QR)
                ->first();
            if ($checkQR) {
                $check_out = LichChamCong::where('user_id', $checkQR->userId)
                    ->where('date', '>=', $today)
                    ->where('date', '<=', $today)
                    ->first();
                if ($check_out) {
                    $lich_cham_cong = LichChamCong::find($check_out->id);
                    $lich_cham_cong->check_out = Carbon::now()->format('H:i:m');
                    $lich_cham_cong->status = '1';
                    $lich_cham_cong->save();
                } else {
                    $lich_cham_cong = new LichChamCong();
                    $lich_cham_cong->user_id = $checkQR->userId;
                    $lich_cham_cong->check_in = Carbon::now()->format('H:i:m');
                    $lich_cham_cong->check_out = Carbon::now()->format('H:i:m');
                    $lich_cham_cong->date = Carbon::now()->format('Y-m-d');
                    $lich_cham_cong->status = '0';
                    $lich_cham_cong->save();
                }
                if (isset($lich_cham_cong->check_out)) {
                    $checkluong = userInfo::where('user_id', $lich_cham_cong->user_id)->first();
                    $startmonth = Carbon::now()->subMonthsNoOverflow()->startOfMonth()->toDateString();
                    $endmonth = Carbon::now()->endOfMonth()->toDateString();
                    $tongtime = LichChamCong::where('user_id', $lich_cham_cong->user_id)
                        ->where('date', '>=', $startmonth)
                        ->where('date', '<=', $endmonth)
                        ->get();
                    $muoihaigio = "12:00:00";
                    $muoibagio = "13:00:00";
                    $tongtimecheckin = 0;
                    foreach ($tongtime as $item) {
                        if (strtotime($muoihaigio) - strtotime($item->check_in) < 0) {
                            $x = 0;
                        } elseif (strtotime($item->check_out) - strtotime($muoihaigio) < 0) {
                            $x = strtotime($item->check_out) - strtotime($item->check_in);
                        } else {
                            $x = strtotime($muoihaigio) - strtotime($item->check_in);
                        }
                        if (strtotime($item->check_out) - strtotime($muoibagio) < 0) {
                            $b = 0;
                        } elseif (strtotime($item->check_in) - strtotime($muoibagio) > 0) {
                            $b = strtotime($item->check_out) - strtotime($item->check_in);
                        } else {
                            $b = strtotime($item->check_out) - strtotime($muoibagio);
                        }
                        $tongtimecheckin += ($x + $b);
                    }
                    $tongtimelam = $tongtimecheckin / 3600;
                    $luongcoban = $checkluong->luong_co_ban;
                    $timecodinh = 8;
                    if (($tongtimelam - $timecodinh * 22) > 0) {
                        $tongluong = ($luongcoban + ($tongtimelam - ($timecodinh * 22) * ($luongcoban / (22 * 8))));
                    } elseif (($tongtimelam - $timecodinh * 22) == 0) {
                        $tongluong = $luongcoban;
                    } else {
                        $tongluong = ($luongcoban - (($timecodinh * 22) - $tongtimelam) * ($luongcoban / (22 * 8)));
                    }
                    $formatluong = number_format($tongluong, 0, '.', ',');
                    if (isset($formatluong)) {
                        $checktongluong = TongThuNhap::where('user_id', $lich_cham_cong->user_id)
                            ->where('date', '>=', $startmonth)
                            ->where('date', '<=', $endmonth)
                            ->first();
                        if ($checktongluong) {
                            $luong = TongThuNhap::find($checktongluong->id);
                            $luong->total_gross_salary = $formatluong;
                            $luong->date = Carbon::now()->toDateString();
                            $luong->save();
                        } else {
                            $luong = new TongThuNhap();
                            $luong->user_id = $lich_cham_cong->user_id;
                            $luong->total_gross_salary = $formatluong;
                            $luong->total_net_salary = '';
                            $luong->status = "0";
                            $luong->date = Carbon::now();
                            $luong->save();
                        }
                    }
                }
            }
        } catch (Exception $e) {
        }
    }
}