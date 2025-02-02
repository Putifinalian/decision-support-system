<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlternativeRequest;
use App\Http\Requests\UpdateAlternativeRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

use App\Models\Alternative;

class AlternativeController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        return view("pages.alternative.index");
    }

    public function list(Request $request, $project_id)
    {
        $draw = $request->get('draw') ?? true;
        $start = intval($request->get('start'));
        // $rowperpage = $start + intval($request->get('length'));
        $rowperpage = $request->get("length");

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');

        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        if ($columnName == "DT_RowIndex") $columnName = "id";
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = $search_arr['value']; // Search value

        // $total_data = Alternative::where("user_id", Auth::id())->where("project_id", $project_id)->count();
        $total_data = DB::table("alternatives")->where("user_id", Auth::id())->where("project_id", $project_id)->count();

        // $data = Alternative::where("user_id", Auth::id())->where("project_id", $project_id)->with(["alternative_details"])
        //     ->skip($start)->take($rowperpage)
        //     ->get();
        $records = Alternative::query();
        // $records = DB::table("alternatives");
        if ($columnName == "id") {
            $records = $records->orderBy("created_at", "DESC");
        } else {
            $records = $records->orderBy($columnName, $columnSortOrder);
        }
        
        $records = $records->where("user_id", Auth::id())->where("project_id", $project_id);
        // $records = $records->with(["alternative_details"]);
        if ($searchValue) {
            $records = $records->where('name', 'LIKE', '%' . $searchValue . '%');
        }
        $records_count = $records->count();
        
        // $records = $records->select('alternatives.*');
        $records = $records->skip($start)->take($rowperpage);
        $records = $records->get();
        // dd($records->get());
        // $records_count = $total_data;


        return Datatables::of($records)
            ->skipPaging($start)
            ->setTotalRecords($total_data)
            ->setFilteredRecords($records_count)
            ->addIndexColumn()
            ->addColumn('name', function ($alternative) use ($project_id) {
                $label = $alternative->name;
                $attributes = collect([
                    "href" => route("alternative.show", ["project" => $project_id, "alternative" => $alternative->id])
                ]);
                return view("components.linkC", compact("label", "attributes"));
            })
            ->addColumn('details', function ($alternative) {
                return view("pages.alternative.components.table-button-details", compact("alternative"));
            })
            ->addColumn('action', function ($alternative) {
                return view("pages.alternative.components.table-button-action", compact("alternative"));
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\StoreAlternativeRequest  $request
     * @return \Illuminate\Http\Response
     */

    public function store(StoreAlternativeRequest $request, $project_id)
    {
        // return response()->json($request->all());
        $alternatives = [];
        foreach ($request->alternatives as $request_alternative) {
            $alternative = [
                "uuid" => $request_alternative["uuid"] ?? Str::uuid()->toString(),
                "name" => $request_alternative["name"],
                "project_id" => $project_id,
                "user_id" => Auth::id(),
                "details" => "{}",
                "created_at" => date('Y-m-d H:i:s'),
                "updated_at" => date('Y-m-d H:i:s'),
            ];
            $description = $request_alternative["description"] ?? null;
            if ($description != null) $alternative["description"] = $description;
            $alternatives[] = $alternative;
        }

        foreach ($request->details as $index => $request_details) {
            $detail_strings = [];
            foreach ($request_details as $key => $request_detail) {
                $detail_strings[$request_detail["key"]] = $request_detail["value"];
            }
            $alternatives[$index]["details"] = collect($detail_strings)->toJson();
        }

        foreach (array_chunk($alternatives, 1000) as $key => $item) {
            Alternative::insert($item);
        }

        return response()->json(compact("alternatives"));
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Alternative  $alternative
     * @return \Illuminate\Http\Response
     */
    public function show(Alternative $alternative)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Alternative  $alternative
     * @return \Illuminate\Http\Response
     */
    public function edit(Alternative $alternative)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateAlternativeRequest  $request
     * @param  \App\Models\Alternative  $alternative
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateAlternativeRequest $request, $project_id, Alternative $alternative)
    {
        $detail_strings = [];
        foreach ($request->details as $key => $req_detail) {
            $key = $req_detail['key'];
            $value = $req_detail['value'];
            $detail_strings[$key] = $value;
        }

        $alternative->name = $request->name;
        $alternative->uuid = $request->uuid;
        $alternative->description = $request->description;
        $alternative->details = collect($detail_strings)->toJson();
        $alternative->save();

        // $details = [];
        // Alternativedetail::where("alternative_id", $alternative->id)->delete();
        // foreach ($request->details as $req_detail) {
        //     $detail = new Alternativedetail();
        //     $detail->key = $req_detail["key"];
        //     $detail->key_slug = Str::slug($req_detail["key"]);
        //     $detail->value = $req_detail["value"];
        //     $detail->value_slug = Str::slug($req_detail["value"]);
        //     $detail->alternative_id = $alternative->id;
        //     $detail->user_id = Auth::id();
        //     $detail->save();
        //     array_push($details, $detail);
        // }
        // return response()->json(compact("alternative", "details"));

        return response()->json(compact("alternative"));
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Alternative  $alternative
     * @return \Illuminate\Http\Response
     */
    public function destroy($project_id, $alternative_id)
    {
        return response()->json(Alternative::where("id", $alternative_id)->delete());
    }

    public function list3(Request $request, $project_id)
    {
        ## Read value
        $draw = $request->get('draw');
        $start = $request->get("start");
        $rowperpage = $request->get("length"); // Rows display per page

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');

        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = $search_arr['value']; // Search value

        // Total records
        $totalRecords = Alternative::select('count(*) as allcount')->count();
        $totalRecordswithFilter = Alternative::select('count(*) as allcount')->where('name', 'like', '%' . $searchValue . '%')->count();

        // Fetch records
        $records = Alternative::orderBy($columnName, $columnSortOrder)
            ->where('alternatives.name', 'like', '%' . $searchValue . '%')
            ->select('alternatives.*')
            ->skip($start)
            ->take($rowperpage)
            ->get();

        $data_arr = array();

        foreach ($records as $record) {
            // $id = $record->id;
            // $username = $record->username;
            // $name = $record->name;
            // $email = $record->email;
            // $data_arr[] = array(
            //     "id" => $id,
            //     "username" => $username,
            //     "name" => $name,
            //     "email" => $email
            // );
            $data_arr[] = $record;
        }

        $response = array(
            "draw" => intval($draw),
            "iTotalRecords" => $totalRecords,
            "iTotalDisplayRecords" => $totalRecordswithFilter,
            "aaData" => $data_arr
        );

        return response()->json($response);
    }

    public function list2(Request $request, $project_id)
    {
        $draw = $request->get('draw') ?? true;
        $start = intval($request->get('start'));
        // $rowperpage = $start + intval($request->get('length'));
        $rowperpage = $request->get("length");

        $columnIndex_arr = $request->get('order');
        $columnName_arr = $request->get('columns');
        $order_arr = $request->get('order');
        $search_arr = $request->get('search');

        $columnIndex = $columnIndex_arr[0]['column']; // Column index
        $columnName = $columnName_arr[$columnIndex]['data']; // Column name
        if ($columnName == "DT_RowIndex") $columnName = "id";
        $columnSortOrder = $order_arr[0]['dir']; // asc or desc
        $searchValue = $search_arr['value']; // Search value

        // $total_data = Alternative::where("user_id", Auth::id())->where("project_id", $project_id)->count();
        $total_data = DB::table("alternatives")->where("user_id", Auth::id())->where("project_id", $project_id)->count();

        // $data = Alternative::where("user_id", Auth::id())->where("project_id", $project_id)->with(["alternative_details"])
        //     ->skip($start)->take($rowperpage)
        //     ->get();

        $records = Alternative::query();
        // $records = DB::table("alternatives");
        if ($columnName == "id") {
            $records = $records->orderBy("created_at", "DESC");
        } else {
            $records = $records->orderBy($columnName, $columnSortOrder);
        }

        $records = $records->where("user_id", Auth::id())->where("project_id", $project_id);
        $records = $records->with(["alternative_details"]);
        if ($searchValue) {
            $records = $records->where('name', 'LIKE', '%' . $searchValue . '%');
        }
        $records_count = $records->count();

        // $records = $records->select('alternatives.*');
        $records = $records->skip($start)->take($rowperpage);
        $records = $records->get();

        // $records_count = $total_data;


        return Datatables::of($records)
            ->skipPaging($start)
            ->setTotalRecords($total_data)
            ->setFilteredRecords($records_count)
            ->addIndexColumn()
            ->addColumn('name', function ($alternative) use ($project_id) {
                $label = $alternative->name;
                $attributes = collect([
                    "href" => route("alternative.show", ["project" => $project_id, "alternative" => $alternative->id])
                ]);
                return view("components.linkC", compact("label", "attributes"));
            })
            ->addColumn('details', function ($alternative) {
                return view("pages.alternative.components.table-button-details", compact("alternative"));
            })
            ->addColumn('action', function ($alternative) {
                return view("pages.alternative.components.table-button-action", compact("alternative"));
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function list1(Request $request, $project_id)
    {
        $data = Alternative::where("user_id", Auth::id())->where("project_id", $project_id)->with(["alternative_details"])->latest()->get();
        // return response()->json($data);
        return Datatables::of($data)
            ->addIndexColumn()
            ->addColumn('name', function ($alternative) use ($project_id) {
                $label = $alternative->name;
                $attributes = collect([
                    "href" => route("alternative.show", ["project" => $project_id, "alternative" => $alternative->id])
                ]);
                return view("components.linkC", compact("label", "attributes"));
            })
            ->addColumn('details', function ($alternative) {
                return view("pages.alternative.components.table-button-details", compact("alternative"));
            })
            ->addColumn('action', function ($alternative) {
                return view("pages.alternative.components.table-button-action", compact("alternative"));
            })
            ->rawColumns(['action'])
            ->make(true);
    }
}
