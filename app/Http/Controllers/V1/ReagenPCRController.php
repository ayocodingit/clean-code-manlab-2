<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ReagenPCR;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReagenPCRController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $models = ReagenPCR::query();
        $search = $request->get('search', false);
        $order = $request->get('order', 'nama');

        if ($search != '') {
            $models = $models->where(function ($q) use ($search) {
                $q->where('nama', 'ilike', '%' . $search . '%')
                    ->orWhere('ct_normal', $search);
            });
        }
        $count = $models->count();

        $page = $request->get('page', 1);
        $perpage = $request->get('perpage', 20);
        $order_direction = $request->get('order_direction', 'asc');

        switch ($order) {
            case 'nama':
            case 'ct_normal':
                $models = $models->orderBy($order, $order_direction);
                break;
        }

        $models = $models->skip(($page - 1) * $perpage)->take($perpage)->get();

        $result = [
            'data' => $models,
            'count' => $count,
        ];

        return response()->json($result);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $rules = [
            'nama' => 'required|unique:reagen_pcr,nama',
            'ct_normal' => 'required|integer',
        ];
        $request->validate($rules);

        DB::beginTransaction();
        try {
            $data = new ReagenPCR;
            $data->nama = $request->get('nama');
            $data->ct_normal = $request->get('ct_normal');
            $data->save();
            DB::commit();
            return response()->json(['code' => 200, 'message' => 'success', 'result' => $data], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['code' => 500, 'message' => 'sistem error'], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $data = ReagenPCR::findOrFail($id);
        return response()->json(['code' => 200, 'message' => 'success', 'result' => $data], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        $rules = [
            'nama' => 'required|unique:reagen_pcr,nama,' . $id,
            'ct_normal' => 'required|integer',
        ];
        $request->validate($rules);

        DB::beginTransaction();
        try {
            $data = ReagenPCR::findOrFail($id);
            $data->nama = $request->get('nama');
            $data->ct_normal = $request->get('ct_normal');
            $data->save();
            DB::commit();
            return response()->json(['code' => 200, 'message' => 'success', 'result' => $data], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['code' => 500, 'message' => 'sistem error'], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $data = ReagenPCR::findOrFail($id);
            $data->delete();
            DB::commit();
            return response()->json(['code' => 200, 'message' => 'success', 'result' => []], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            return response()->json(['code' => 500, 'message' => 'sistem error'], 500);
        }
    }
}
