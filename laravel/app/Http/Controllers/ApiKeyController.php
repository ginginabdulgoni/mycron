<?php

namespace App\Http\Controllers;


use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ApiKeyController extends Controller
{
    public function index()
    {
        $apiKeys = ApiKey::latest()->get();
        return view('apikeys.index', compact('apiKeys'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'key' => 'required|unique:api_keys,key',
        ]);

        $data = [
            'name' => $request->name,
            'key' => $request->key,
            'active' => $request->has('active'),
        ];

        $apiKey = ApiKey::create($data);

        return response()->json(['status' => 'success', 'message' => 'API Key created.', 'data' => $apiKey]);
    }

    public function edit($id)
    {
        return response()->json(ApiKey::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $apiKey = ApiKey::findOrFail($id);

        $apiKey->update([
            'name' => $request->name,
            'active' => $request->has('active'),
            'key' => $request->key,
        ]);

        return response()->json(['status' => 'success', 'message' => 'API Key updated.']);
    }

    public function destroy($id)
    {
        ApiKey::destroy($id);
        return response()->json(['status' => 'success', 'message' => 'API Key deleted.']);
    }
}
