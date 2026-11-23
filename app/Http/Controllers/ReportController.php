<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    // List reports (super-admin sees all, others only their own)
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if ($user->is_super_admin) {
                $reports = Report::orderBy('created_at', 'desc')->get();
            } else {
                $reports = Report::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            return response()->json([
                'success' => true,
                'reports' => $reports->map(function ($report) {
                    return [
                        'id'         => $report->id,
                        'file_name'  => $report->file_name,
                        'created_at' => $report->created_at,
                        'total_ht'   => $report->total_ht,
                        'rows'       => $report->rows,
                        'file_url'   => asset('storage/' . $report->file_path)
                    ];
                })
            ]);
        } catch (\Exception $e) {
            Log::error('Report index error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Store a new report (PDF base64 + rows)
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file_name' => 'required|string',
                'pdf_data'  => 'required|string',
                'rows'      => 'required|array',
                'total_ht'  => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $user = $request->user();

            // Decode base64 PDF
            $pdfData = $request->pdf_data;
            if (str_contains($pdfData, 'base64,')) {
                $pdfData = explode('base64,', $pdfData)[1];
            }
            $pdfBinary = base64_decode($pdfData);

            $directory = storage_path('app/public/reports/' . $user->id);
            if (!file_exists($directory)) {
                mkdir($directory, 0777, true);
            }

            $fileName = $request->file_name;
            $filePath = 'reports/' . $user->id . '/' . $fileName;
            Storage::disk('public')->put($filePath, $pdfBinary);

            $report = Report::create([
                'user_id'   => $user->id,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'rows'      => $request->rows,
                'total_ht'  => $request->total_ht
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report saved',
                'report'  => [
                    'id'         => $report->id,
                    'file_name'  => $report->file_name,
                    'created_at' => $report->created_at,
                    'total_ht'   => $report->total_ht,
                    'rows'       => $report->rows,
                    'file_url'   => asset('storage/' . $filePath)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Report store error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to save report'], 500);
        }
    }

    // Update existing report
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            $report = Report::where('user_id', $user->id)->where('id', $id)->first();
            if (!$report) {
                return response()->json(['success' => false, 'message' => 'Report not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'file_name' => 'required|string',
                'pdf_data'  => 'required|string',
                'rows'      => 'required|array',
                'total_ht'  => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            // Decode PDF
            $pdfData = $request->pdf_data;
            if (str_contains($pdfData, 'base64,')) {
                $pdfData = explode('base64,', $pdfData)[1];
            }
            $pdfBinary = base64_decode($pdfData);

            // Delete old file
            if (Storage::disk('public')->exists($report->file_path)) {
                Storage::disk('public')->delete($report->file_path);
            }

            $fileName = $request->file_name;
            $filePath = 'reports/' . $user->id . '/' . $fileName;
            Storage::disk('public')->put($filePath, $pdfBinary);

            $report->update([
                'file_name' => $fileName,
                'file_path' => $filePath,
                'rows'      => $request->rows,
                'total_ht'  => $request->total_ht
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report updated',
                'report'  => [
                    'id'         => $report->id,
                    'file_name'  => $report->file_name,
                    'created_at' => $report->created_at,
                    'total_ht'   => $report->total_ht,
                    'rows'       => $report->rows,
                    'file_url'   => asset('storage/' . $filePath)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Report update error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update report'], 500);
        }
    }

    // Delete report
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $report = Report::where('user_id', $user->id)->where('id', $id)->first();
            if (!$report) {
                return response()->json(['success' => false, 'message' => 'Report not found'], 404);
            }

            if (Storage::disk('public')->exists($report->file_path)) {
                Storage::disk('public')->delete($report->file_path);
            }
            $report->delete();

            return response()->json(['success' => true, 'message' => 'Report deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Download report file
    public function download(Request $request, $id)
    {
        try {
            $user = $request->user();
            $report = Report::where('user_id', $user->id)->where('id', $id)->first();
            if (!$report) {
                abort(404);
            }
            $path = storage_path('app/public/' . $report->file_path);
            if (!file_exists($path)) {
                abort(404);
            }
            return response()->download($path, $report->file_name, [
                'Content-Type' => 'application/pdf',
            ]);
        } catch (\Exception $e) {
            abort(404);
        }
    }
}