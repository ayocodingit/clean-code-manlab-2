<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\Sampel;
use Illuminate\Http\Request;
use DB;
use App\Models\Register;
use App\Models\Pasien;
use App\Models\PasienRegister;
use App\Models\PemeriksaanSampel;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DashboardController extends Controller
{
    public function notifications(Request $request)
    {
        $user = $request->user();
        $data = [];
        $count = 0;
        if ($user->role_id == 4 || $user->role_id == 1) {
            $jumlah = Sampel::where('sampel_status','extraction_sample_reextract')->count();
            if ($jumlah) {
                $data[] = [
                    'link' => '/ekstraksi/dikembalikan',
                    'message' => "Ada $jumlah sampel yang dikembalikan",
                    'icon' => 'uil-flask',
                ];
                $count += $jumlah;
            }
        }
        return response()->json([
            'data' => $data,
            'count' => $count,
        ]);
    }


 
    public function tracking()
    {
        $count_by_status = Sampel::groupBy('sampel_status')->select('sampel_status', DB::raw('count(*) as total'))->pluck('total','sampel_status');
        $registration_incomplete = Sampel::whereNull('nomor_register')->count();
        $tracking = [
            'registration_incomplete' => $registration_incomplete,
            'waiting_sample' => @$count_by_status['waiting_sample'],
            'extraction' => @$count_by_status['sample_taken'] + @$count_by_status['extraction_sample_extracted'] + @$count_by_status['extraction_sample_reextract'],
            'pcr' => @$count_by_status['extraction_sample_sent'] + @$count_by_status['pcr_sample_received'],
            'verification' => @$count_by_status['pcr_sample_analyzed'],
            'validation' => @$count_by_status['sample_verified'],
            'done' => @$count_by_status['sample_valid'],
            'invalid' => @$count_by_status['sample_invalid'],
        ];

        $tracking2 = [
            'registration'=>Register::count(),
            'sampel'=>Sampel::count(),
            'ekstraksi'=>Sampel::whereNotNull('waktu_extraction_sample_extracted')->count(),
            'repcr'=>0,
            'verifikasi'=>Sampel::whereNotNull('waktu_sample_verified')->count(),
            'validasi'=>Sampel::whereNotNull('waktu_sample_valid')->count()
        ];

        return response()->json([
            'status' => $tracking2,
        ]);
    }

    public function registrasi(Request $request)
    {
        $count_by_jenis = Register::groupBy('jenis_registrasi')
                            ->select('jenis_registrasi', DB::raw('count(*) as total'))
                            ->pluck('total','jenis_registrasi');
        $count_by_status = Sampel::groupBy('sampel_status')
                            ->select('sampel_status', DB::raw('count(*) as total'))
                            ->pluck('total','sampel_status');

        $srujukan = Sampel::groupBy('sampel_status')
                            ->leftJoin('register','register.nomor_register','sampel.nomor_register')
                            ->select('sampel_status', DB::raw('count(*) as total'))
                            ->where('jenis_registrasi','rujukan')
                            ->pluck('total','sampel_status'); 
        
        $smandiri = Sampel::groupBy('sampel_status')
                            ->leftJoin('register','register.nomor_register','sampel.nomor_register')
                            ->select('sampel_status', DB::raw('count(*) as total'))
                            ->where('jenis_registrasi','mandiri')
                            ->pluck('total','sampel_status');       

        $bmandiri = PasienRegister::join('pasien','pasien.id','pasien_register.pasien_id')
                        ->join('register','register.id','pasien_register.register_id')
                        ->where(function($query){
                            $query->whereNull('nik')
                                ->orWhereNull('no_hp')
                                ->orWhereNull('alamat_lengkap');
                        })
                        ->groupBy('jenis_registrasi')
                        ->select('register.jenis_registrasi', DB::raw('count(*) as total'))
                        ->pluck('total','jenis_registrasi');
        
        $ninput = Sampel::whereNull('nomor_register')
                        ->count();
        
        $today = Register::whereDate('created_at', Carbon::today())
                        ->groupBy('jenis_registrasi')
                        ->select('jenis_registrasi', DB::raw('count(*) as total'))
                        ->pluck('total','jenis_registrasi');

        $tracking = [
            'total_registrasi' => @$count_by_jenis['mandiri'] + @$count_by_jenis['rujukan'],
            'registrasi_mandiri' => @$count_by_jenis['mandiri'],
            'registrasi_rujukan' => @$count_by_jenis['rujukan'],
            'mandiri' => [
                'today' => @$today['mandiri'],
                'belum_lengkap' => @$bmandiri['mandiri'],
                'done' => @$smandiri['sample_valid']
            ],
            'rujukan' => [
                'today' => @$today['rujukan'],
                'belum_lengkap' => $bmandiri['rujukan'],
                'done' => @$srujukan['sample_valid'],
                'none' => $ninput
            ],
            'done' => @$count_by_status['sample_valid'],

        ];
        return response()->json($tracking);
    }
    public function ekstraksi()
    {
        $count_by_status = Sampel::groupBy('sampel_status')
                        ->select('sampel_status', DB::raw('count(*) as total'))
                        ->pluck('total','sampel_status');
        // dd($count_by_status);
        $count_by_status['extraction_sent'] = 0
            + @$count_by_status['extraction_sample_sent']
            + @$count_by_status['pcr_sample_received']
            + @$count_by_status['pcr_sample_analyzed']
            + @$count_by_status['sample_verified']
            + @$count_by_status['sample_valid'];
        $count_by_status = $count_by_status->only(['extraction_sent','sample_taken','extraction_sample_extracted','extraction_sample_reextract','waiting_sampel','sample_invalid']);
        
        $count_by_labs = DB::table('lab_pcr')->leftJoin('sampel','sampel.lab_pcr_id','lab_pcr.id')
            ->groupBy('lab_pcr.nama')->orderByRaw('count(*) desc')
            ->select('nama', DB::raw('count(*) as total'))
            ->whereIn('sampel_status',['extraction_sample_sent','pcr_sample_received','pcr_sample_analyzed','sample_verified','sample_valid'])
            ->get();
        
        $invalid = PemeriksaanSampel::groupBy('kesimpulan_pemeriksaan')
                    ->select('kesimpulan_pemeriksaan', DB::raw('count(*) as total'))
                    ->pluck('total','kesimpulan_pemeriksaan');

        return response()->json([
            'status' => $count_by_status,
            'send' => $count_by_labs,
            'kurang' => @$invalid['sampel kurang'],
            'ulang' => @$invalid['swab ulang'],
            'invalid' => @$count_by_status['sample_invalid']
        ]);
    }
    public function pcr(Request $request)
    {
        $user = $request->user();
        $query = Sampel::groupBy('sampel_status')
            ->select('sampel_status', DB::raw('count(*) as total'))
            ;
        if (!empty($user->lab_pcr_id)) {
            $query->where('lab_pcr_id', $user->lab_pcr_id);
        }
        $count_by_status = cctor($query)->pluck('total','sampel_status');
        $count_by_status = $count_by_status->only(['extraction_sample_sent','pcr_sample_received']);
        $counter = Sampel::leftJoin('pemeriksaansampel','pemeriksaansampel.sampel_id','=','sampel.id')
            ->whereIn('sampel_status', [
                'pcr_sample_analyzed',
                'sample_verified',
                'sample_valid',
            ]);
        $count_by_status['sample_valid'] = cctor($counter)->where(function($q) {
            $q->where('kesimpulan_pemeriksaan', '<>', 'inkonklusif')->orWhereNull('kesimpulan_pemeriksaan');
        })->count();
        $count_by_status['sample_inconclusive'] = cctor($counter)
            ->where('kesimpulan_pemeriksaan', 'inkonklusif')
            ->count();

        return response()->json([
            'status' => $count_by_status,
        ]);
    }

    public function positifNegatif(Request $request)
    {
        $count_by_status = PemeriksaanSampel::groupBy('kesimpulan_pemeriksaan')
                            ->select("kesimpulan_pemeriksaan",DB::raw("count(*) as total"))
                            ->pluck('total','kesimpulan_pemeriksaan');
        return response()->json([
            'negatif' => @$count_by_status['negatif'],
            'positif' => @$count_by_status['positif']
        ]);
    }

    public function chartMandiri(Request $request)
    {
        $tipe = $request->get('tipe','Daily');
        
        $now = Carbon::now();
        $weekStartDate = $now->startOfWeek()->format('Y-m-d H:i');
        $weekEndDate = $now->endOfWeek()->format('Y-m-d H:i');
        $period = CarbonPeriod::create($weekStartDate, $weekEndDate);
        $label = [];
        $value = [];

        switch ($tipe)
        {
            case"Daily":
                foreach ($period as $date) {
                    $count = Register::where('jenis_registrasi','mandiri')
                        ->whereRaw("CAST(created_at AS DATE) = '".$date->format('Y-m-d')."'")
                        ->count();
                    array_push($label, $date->format('D'));
                    array_push($value, $count);
                }
            break;
            case "Monthly":

                $periode = \DB::select("SELECT DISTINCT(TO_CHAR(created_at,'YYYY MON')) as label,TO_CHAR(created_at,'YYYY-MM') as month FROM register");
                foreach($periode as $row)
                {
                    $count = Register::where('jenis_registrasi','mandiri')
                        ->whereRaw("left(created_at::TEXT,7)='$row->month'")
                        ->count();

                    array_push($label,$row->label);
                    array_push($value,$count);
                }
                break;
        }

        return response()->json([
            'label' => $label,
            'value' => $value
        ]);
        // SELECT CAST(created_at AS DATE) AS DATE, COUNT(*) as total
        // FROM register 
        // WHERE created_at > date ('Y-m-d')
        // GROUP BY CAST(created_at AS DATE)
        // ORDER BY CAST(created_at AS DATE)

    }

    public function chartRujukan(Request $request)
    {
        $tipe = $request->get('tipe','Daily');
        $now = Carbon::now();
        $weekStartDate = $now->startOfWeek()->format('Y-m-d H:i');
        $weekEndDate = $now->endOfWeek()->format('Y-m-d H:i');
        $period = CarbonPeriod::create($weekStartDate, $weekEndDate);
        $label = [];
        $value = [];
        switch($tipe) {
            case "Daily":
                foreach ($period as $date) {
                    $count = Register::where('jenis_registrasi','rujukan')
                        ->whereRaw("CAST(created_at AS DATE) = '".$date->format('Y-m-d')."'")
                        ->count();
                    array_push($label, $date->format('D'));
                    array_push($value, $count);
                }
            break;
            case "Monthly":
                $periode = \DB::select("SELECT DISTINCT(TO_CHAR(created_at,'YYYY MON')) as label,TO_CHAR(created_at,'YYYY-MM') as month FROM register");
                foreach($periode as $row)
                {
                    $count = Register::where('jenis_registrasi','rujukan')
                        ->whereRaw("left(created_at::TEXT,7)='$row->month'")
                        ->count();

                    array_push($label,$row->label);
                    array_push($value,$count);
                }
            break;
        }
        
        return response()->json([
            'label' => $label,
            'value' => $value
        ]);
    }

    public function chartEkstraksi(Request $request)
    {
        $models = Sampel::whereNotNull('waktu_extraction_sample_extracted');
        $tipe = $request->get('tipe','Daily');

        switch($tipe) {
            case "Daily":
                $models = $models->whereBetween('waktu_extraction_sample_extracted',[date('Y-m-d', strtotime("-7 days")), date('Y-m-d')])
                ->select(DB::raw('CAST(waktu_extraction_sample_extracted AS DATE) tanggal'),DB::raw('count(*) as jumlah'))
                ->groupBy(DB::raw('CAST(waktu_extraction_sample_extracted AS DATE)'))
                ->pluck('jumlah','tanggal');
                
            break;
            case "Monthly":
                $models = $models
                            ->where(DB::raw('extract(YEAR from waktu_extraction_sample_extracted)'),date('Y') )
                            // ->select(DB::raw('extract(MONTH from waktu_extraction_sample_extracted) bulan'),DB::raw('count(*) as jumlah'))
                            // ->groupBy(DB::raw('extract(MONTH from waktu_extraction_sample_extracted)'))
                            ->select(DB::raw("TO_CHAR(waktu_extraction_sample_extracted, 'Month') as bulan"),DB::raw('count(*) as jumlah'))
                            ->groupBy(DB::raw("TO_CHAR(waktu_extraction_sample_extracted, 'Month')"))
                            ->pluck('jumlah','bulan');
            break;
        }
        return response()->json([
            'label' => $models->keys(),
            'value' => $models->values()
        ]);
    }

    public function chartPcr(Request $request)
    {
        $models = Sampel::whereNotNull('waktu_pcr_sample_analyzed');
        $tipe = $request->get('tipe','Daily');

        switch($tipe) {
            case "Daily":
                $models = $models->whereBetween('waktu_pcr_sample_analyzed',[date('Y-m-d', strtotime("-7 days")), date('Y-m-d')])
                ->select(DB::raw('CAST(waktu_pcr_sample_analyzed AS DATE) tanggal'),DB::raw('count(*) as jumlah'))
                ->groupBy(DB::raw('CAST(waktu_pcr_sample_analyzed AS DATE)'))
                ->pluck('jumlah','tanggal');
                
            break;
            case "Monthly":
                $models = $models
                            ->where(DB::raw('extract(YEAR from waktu_pcr_sample_analyzed)'),date('Y') )
                            // ->select(DB::raw('extract(MONTH from waktu_pcr_sample_analyzed) bulan'),DB::raw('count(*) as jumlah'))
                            // ->groupBy(DB::raw('extract(MONTH from waktu_pcr_sample_analyzed)'))
                            ->select(DB::raw("TO_CHAR(waktu_pcr_sample_analyzed, 'Month') as bulan"),DB::raw('count(*) as jumlah'))
                            ->groupBy(DB::raw("TO_CHAR(waktu_pcr_sample_analyzed, 'Month')"))
                            ->pluck('jumlah','bulan');
            break;
        }
        return response()->json([
            'label' => $models->keys(),
            'value' => $models->values()
        ]);
    }

    public function chartPositif(Request $request)
    {
        // SELECT CAST(created_at AS DATE) AS DATE, COUNT(*) as total
        // FROM register 
        // WHERE created_at > date ('Y-m-d')
        // GROUP BY CAST(created_at AS DATE)
        // ORDER BY CAST(created_at AS DATE)
        $models = PemeriksaanSampel::leftJoin('sampel','sampel.id','pemeriksaansampel.sampel_id')
                ->where('kesimpulan_pemeriksaan','positif');
        $tipe = $request->get('tipe','Daily');

        switch($tipe) {
            case "Daily":
                $models = $models->whereBetween('waktu_sample_valid',[date('Y-m-d', strtotime("-7 days")), date('Y-m-d')])
                ->select(DB::raw('CAST(waktu_sample_valid AS DATE) tanggal'),DB::raw('count(*) as jumlah'))
                ->groupBy(DB::raw('CAST(waktu_sample_valid AS DATE)'))
                ->pluck('jumlah','tanggal');
                
            break;
            case "Monthly":
                $models = $models
                            ->where(DB::raw('extract(YEAR from waktu_sample_valid)'),date('Y') )
                            ->whereNotNull('waktu_sample_valid')
                            // ->select(DB::raw('extract(MONTH from waktu_sample_valid) bulan'),DB::raw('count(*) as jumlah'))
                            // ->groupBy(DB::raw('extract(MONTH from waktu_sample_valid)'))
                            ->select(DB::raw("TO_CHAR(waktu_sample_valid, 'Month') as bulan"),DB::raw('count(*) as jumlah'))
                            ->groupBy(DB::raw("TO_CHAR(waktu_sample_valid, 'Month')"))
                            ->pluck('jumlah','bulan');
            break;
        }
        return response()->json([
            'label' => $models->keys(),
            'value' => $models->values()
        ]);
    }

    public function chartNegatif(Request $request)
    {
        $models = PemeriksaanSampel::leftJoin('sampel','sampel.id','pemeriksaansampel.sampel_id')
        ->where('kesimpulan_pemeriksaan','negatif');
        $tipe = $request->get('tipe','Daily');

        switch($tipe) {
            case "Daily":
                $models = $models->whereBetween('waktu_sample_valid',[date('Y-m-d', strtotime("-7 days")), date('Y-m-d')])
                ->select(DB::raw('CAST(waktu_sample_valid AS DATE) tanggal'),DB::raw('count(*) as jumlah'))
                ->groupBy(DB::raw('CAST(waktu_sample_valid AS DATE)'))
                ->pluck('jumlah','tanggal');
                
            break;
            case "Monthly":
                $models = $models
                            ->where(DB::raw('extract(YEAR from waktu_sample_valid)'),date('Y') )
                            ->whereNotNull('waktu_sample_valid')
                            // ->select(DB::raw('extract(MONTH from waktu_sample_valid) bulan'),DB::raw('count(*) as jumlah'))
                            // ->groupBy(DB::raw('extract(MONTH from waktu_sample_valid)'))
                            ->select(DB::raw("TO_CHAR(waktu_sample_valid, 'Month') as bulan"),DB::raw('count(*) as jumlah'))
                            ->groupBy(DB::raw("TO_CHAR(waktu_sample_valid, 'Month')"))
                            ->pluck('jumlah','bulan');
            break;
        }
        return response()->json([
            'label' => $models->keys(),
            'value' => $models->values()
        ]);
    }


    public function sampel()
    {
        $total_sampel               = Sampel::all()->count();
        $total_sampel_today         = Sampel::whereDate('tanggal_pengambilan_sampel', \Carbon\Carbon::now())->count();
        $total_sampel_regis_mandiri = Register::where('jenis_registrasi', 'mandiri')->count();

        $response['success'] = true;
        $response['message'] ="data sampel";
        $response['data'] = [
                                'total_sampel'                      =>  $total_sampel,
                                'sampel_today'                      =>  $total_sampel_today,
                                'total_sampel_registrasi_mandiri'   =>  $total_sampel_regis_mandiri
                            ];

        return response()->json($response,200);
    }
}

