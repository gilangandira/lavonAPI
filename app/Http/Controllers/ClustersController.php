<?php

namespace App\Http\Controllers;

use App\Models\Cluster;
use Illuminate\Http\Request;

class ClustersController extends Controller
{
    // GET /api/clusters/stats
    public function stats()
    {
        $totalTypes = Cluster::count();
        
        // Find cluster with most customers
        $mostPopular = Cluster::withCount('customers')
            ->orderBy('customers_count', 'desc')
            ->first();

        // Breakdown for chart
        $breakdown = Cluster::withCount('customers')
            ->get()
            ->map(function($c) {
                return [
                    'name' => $c->type,
                    'value' => $c->customers_count
                ];
            });

        $avgPrice = Cluster::avg('price');

        return response()->json([
            'success' => true,
            'data' => [
                'total_types' => $totalTypes,
                'most_popular' => $mostPopular ? $mostPopular->type : '-',
                'average_price' => $avgPrice,
                'breakdown' => $breakdown
            ]
        ]);
    }

    // GET /api/clusters
    public function index(Request $request)
    {
        $query = Cluster::query();

        if ($request->search) {
            $query->where('type', 'like', "%{$request->search}%");
        }

        // If 'all' parameter is present, return all with customers_count (for dropdowns)
        if ($request->has('all')) {
            return response()->json([
                'success' => true,
                'data' => $query->withCount('customers')->get()
            ]);
        }

        $clusters = $query->withCount('customers')->latest()->paginate(10);

        return response()->json([
            'success' => true,
            'data' => $clusters
        ]);
    }

    // POST /api/clusters
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|string',
            'luas_tanah' => 'required|numeric',
            'luas_bangunan' => 'required|numeric',
            'luas_tanah' => 'required|numeric',
            'luas_bangunan' => 'required|numeric',
            'price' => 'required|numeric',
            'stock' => 'required|integer|min:0',
        ]);

        $cluster = Cluster::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cluster created successfully',
            'data' => $cluster
        ], 201);
    }

    // GET /api/clusters/{id}
    public function show($id)
    {
        $cluster = Cluster::findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cluster
        ]);
    }

    // PUT /api/clusters/{id}
    public function update(Request $request, $id)
    {
        $cluster = Cluster::findOrFail($id);

        $validated = $request->validate([
            'type' => 'sometimes|string',
            'luas_tanah' => 'sometimes|numeric',
            'luas_bangunan' => 'sometimes|numeric',
            'luas_tanah' => 'sometimes|numeric',
            'luas_bangunan' => 'sometimes|numeric',
            'price' => 'sometimes|numeric',
            'stock' => 'sometimes|integer|min:0',
        ]);

        $cluster->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cluster updated successfully',
            'data' => $cluster
        ]);
    }

    // DELETE /api/clusters/{id}
    public function destroy($id)
    {
        $cluster = Cluster::findOrFail($id);
        $cluster->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cluster deleted successfully'
        ]);
    }
}
