<?php
// app/Http/Controllers/ClientController.php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        try {
            DB::beginTransaction();
            
            Log::info('Client creation request received', $request->all());

            $validated = $request->validate([
                'nom' => 'required|string|max:255',
                'prenom' => 'required|string|max:255',
                'telephone' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'city' => 'nullable|string|max:255',
                'cin_number' => 'nullable|string|max:255',
                'driver_license_number' => 'nullable|string|max:255',
                'cin_image' => 'nullable|string',
                'driver_license_image' => 'nullable|string',
                'date_naissance' => 'nullable|date',
                'lieu_naissance' => 'nullable|string|max:255',
                'cin_delivre_le' => 'nullable|date',
                'permis_delivre_le' => 'nullable|date'
            ]);

            Log::info('Validation passed', $validated);

            // ✅ AJOUTER: Remplacer null par des chaînes vides pour les champs qui ne peuvent pas être null
            $validated['email'] = $validated['email'] ?? '';
            $validated['city'] = $validated['city'] ?? '';
            $validated['cin_number'] = $validated['cin_number'] ?? '';
            $validated['driver_license_number'] = $validated['driver_license_number'] ?? '';
            $validated['lieu_naissance'] = $validated['lieu_naissance'] ?? '';

            // Handle base64 file uploads for CIN and Driver License (images or PDF)
            $documentFields = ['cin_image', 'driver_license_image'];
            $uploadedDocuments = [];

            foreach ($documentFields as $field) {
                if (isset($validated[$field]) && !empty($validated[$field])) {
                    try {
                        $fileData = $validated[$field];
                        
                        // Check if it's a base64 string
                        if (strpos($fileData, 'data:') === 0) {
                            // Extract file type and data
                            list($type, $fileData) = explode(';', $fileData);
                            list(, $fileData) = explode(',', $fileData);
                            $fileData = base64_decode($fileData);
                            
                            // Determine file extension
                            $extension = 'png'; // default
                            if (strpos($type, 'image/jpeg') !== false) {
                                $extension = 'jpg';
                            } elseif (strpos($type, 'image/jpg') !== false) {
                                $extension = 'jpg';
                            } elseif (strpos($type, 'image/png') !== false) {
                                $extension = 'png';
                            } elseif (strpos($type, 'application/pdf') !== false) {
                                $extension = 'pdf';
                            } elseif (strpos($type, 'image/') !== false) {
                                $extension = substr($type, strpos($type, '/') + 1);
                            }
                            
                            // Generate unique filename
                            $filename = 'client_' . $field . '_' . uniqid() . '.' . $extension;
                            $path = 'clients/' . $filename;
                            
                            // Store the file
                            Storage::disk('public')->put($path, $fileData);
                            
                            // Update the file path in validated data
                            $uploadedDocuments[$field] = $path;
                        } else {
                            // Existing file path - keep it as is
                            $uploadedDocuments[$field] = $validated[$field];
                        }
                    } catch (\Exception $e) {
                        // If file processing fails, set to empty string
                        $uploadedDocuments[$field] = '';
                        \Log::error("Document upload error for {$field}: " . $e->getMessage());
                    }
                } else {
                    $uploadedDocuments[$field] = '';
                }
            }

            // Merge uploaded documents with validated data
            $clientData = array_merge($validated, $uploadedDocuments);

            // Check for duplicate client (based on telephone only since email can be empty)
            $existingClient = Client::where('telephone', $clientData['telephone'])
                ->first();

            if ($existingClient) {
                Log::warning('Client already exists', ['existing_client' => $existingClient->id]);
                DB::rollBack();
                return response()->json([
                    'message' => 'Client already exists',
                    'client' => $existingClient
                ], 409);
            }

            $client = Client::create($clientData);
            Log::info('Client created successfully', ['client_id' => $client->id]);

            DB::commit();

            // Add file URLs to response
            $client->cin_image_url = !empty($client->cin_image) ? Storage::disk('public')->url($client->cin_image) : null;
            $client->driver_license_image_url = !empty($client->driver_license_image) ? Storage::disk('public')->url($client->driver_license_image) : null;

            return response()->json([
                'message' => 'Client created successfully',
                'client' => $client
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Client creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function index(): JsonResponse
    {
        $clients = Client::all()->map(function ($client) {
            // Add full file URLs to each client
            if (!empty($client->cin_image)) {
                $client->cin_image_url = Storage::disk('public')->exists($client->cin_image) 
                    ? Storage::disk('public')->url($client->cin_image)
                    : null;
            } else {
                $client->cin_image_url = null;
            }
            
            if (!empty($client->driver_license_image)) {
                $client->driver_license_image_url = Storage::disk('public')->exists($client->driver_license_image) 
                    ? Storage::disk('public')->url($client->driver_license_image)
                    : null;
            } else {
                $client->driver_license_image_url = null;
            }
            
            return $client;
        });
        
        return response()->json(['clients' => $clients]);
    }

    public function show($id): JsonResponse
    {
        $client = Client::with('reservations')->findOrFail($id);
        
        // Add file URLs
        if (!empty($client->cin_image)) {
            $client->cin_image_url = Storage::disk('public')->exists($client->cin_image) 
                ? Storage::disk('public')->url($client->cin_image)
                : null;
        }
        
        if (!empty($client->driver_license_image)) {
            $client->driver_license_image_url = Storage::disk('public')->exists($client->driver_license_image) 
                ? Storage::disk('public')->url($client->driver_license_image)
                : null;
        }
        
        return response()->json(['client' => $client]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $client = Client::findOrFail($id);
        
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'prenom' => 'required|string|max:255',
            'telephone' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'city' => 'nullable|string|max:255',
            'cin_number' => 'nullable|string|max:255',
            'driver_license_number' => 'nullable|string|max:255',
            'cin_image' => 'nullable|string',
            'driver_license_image' => 'nullable|string',
            'date_naissance' => 'nullable|date',
            'lieu_naissance' => 'nullable|string|max:255',
            'cin_delivre_le' => 'nullable|date',
            'permis_delivre_le' => 'nullable|date'
        ]);

        // ✅ AJOUTER: Remplacer null par des chaînes vides pour les champs qui ne peuvent pas être null
        $validated['email'] = $validated['email'] ?? '';
        $validated['city'] = $validated['city'] ?? '';
        $validated['cin_number'] = $validated['cin_number'] ?? '';
        $validated['driver_license_number'] = $validated['driver_license_number'] ?? '';
        $validated['lieu_naissance'] = $validated['lieu_naissance'] ?? '';

        // Handle document processing (images or PDF)
        $documentFields = ['cin_image', 'driver_license_image'];
        $uploadedDocuments = [];

        foreach ($documentFields as $field) {
            if (isset($validated[$field]) && !empty($validated[$field])) {
                try {
                    $fileData = $validated[$field];
                    
                    // Check if it's a base64 string
                    if (strpos($fileData, 'data:') === 0) {
                        // New base64 file - upload it
                        list($type, $fileData) = explode(';', $fileData);
                        list(, $fileData) = explode(',', $fileData);
                        $fileData = base64_decode($fileData);
                        
                        // Determine file extension
                        $extension = 'png'; // default
                        if (strpos($type, 'image/jpeg') !== false) {
                            $extension = 'jpg';
                        } elseif (strpos($type, 'image/jpg') !== false) {
                            $extension = 'jpg';
                        } elseif (strpos($type, 'image/png') !== false) {
                            $extension = 'png';
                        } elseif (strpos($type, 'application/pdf') !== false) {
                            $extension = 'pdf';
                        } elseif (strpos($type, 'image/') !== false) {
                            $extension = substr($type, strpos($type, '/') + 1);
                        }
                        
                        // Generate unique filename
                        $filename = 'client_' . $field . '_' . uniqid() . '.' . $extension;
                        $path = 'clients/' . $filename;
                        
                        // Store the file
                        Storage::disk('public')->put($path, $fileData);
                        
                        // Update the file path in validated data
                        $uploadedDocuments[$field] = $path;
                        
                        // Delete old file if exists
                        if (!empty($client->$field) && Storage::disk('public')->exists($client->$field)) {
                            Storage::disk('public')->delete($client->$field);
                        }
                    } else {
                        // Existing file path - keep it as is
                        $uploadedDocuments[$field] = $validated[$field];
                    }
                } catch (\Exception $e) {
                    // If file processing fails, keep the old file
                    unset($validated[$field]);
                    \Log::error("Document upload error for {$field}: " . $e->getMessage());
                }
            } elseif (isset($validated[$field]) && empty($validated[$field])) {
                // If empty file string provided, delete the current file
                if (!empty($client->$field) && Storage::disk('public')->exists($client->$field)) {
                    Storage::disk('public')->delete($client->$field);
                }
                $uploadedDocuments[$field] = '';
            } else {
                // Keep existing files if not updating this field
                $uploadedDocuments[$field] = $client->$field;
            }
        }

        // Merge uploaded documents with validated data
        $updateData = array_merge($validated, $uploadedDocuments);

        $client->update($updateData);
        
        // Add file URLs to response
        if (!empty($client->cin_image)) {
            $client->cin_image_url = Storage::disk('public')->url($client->cin_image);
        }
        
        if (!empty($client->driver_license_image)) {
            $client->driver_license_image_url = Storage::disk('public')->url($client->driver_license_image);
        }
        
        return response()->json(['client' => $client]);
    }

    public function destroy($id): JsonResponse
    {
        $client = Client::findOrFail($id);
        
        // Delete associated files
        if (!empty($client->cin_image) && Storage::disk('public')->exists($client->cin_image)) {
            Storage::disk('public')->delete($client->cin_image);
        }
        
        if (!empty($client->driver_license_image) && Storage::disk('public')->exists($client->driver_license_image)) {
            Storage::disk('public')->delete($client->driver_license_image);
        }
        
        $client->delete();
        return response()->json(['message' => 'Client deleted successfully']);
    }

    // Search clients by name, email, or phone
    public function search(Request $request): JsonResponse
    {
        $search = $request->query('q');
        
        $clients = Client::when($search, function($query) use ($search) {
            return $query->where('nom', 'like', "%{$search}%")
                       ->orWhere('prenom', 'like', "%{$search}%")
                       ->orWhere('email', 'like', "%{$search}%")
                       ->orWhere('telephone', 'like', "%{$search}%");
        })->limit(10)->get()->map(function ($client) {
            // Add file URLs
            if (!empty($client->cin_image)) {
                $client->cin_image_url = Storage::disk('public')->exists($client->cin_image) 
                    ? Storage::disk('public')->url($client->cin_image)
                    : null;
            }
            
            if (!empty($client->driver_license_image)) {
                $client->driver_license_image_url = Storage::disk('public')->exists($client->driver_license_image) 
                    ? Storage::disk('public')->url($client->driver_license_image)
                    : null;
            }
            
            return $client;
        });

        return response()->json(['clients' => $clients]);
    }

    // ClientController.php - Add this method
    public function downloadDocument($id, $documentType)
    {
        $client = Client::findOrFail($id);
        
        $validDocuments = ['cin_image', 'driver_license_image'];
        
        if (!in_array($documentType, $validDocuments)) {
            return response()->json(['error' => 'Invalid document type'], 400);
        }
        
        if (empty($client->$documentType) || !Storage::disk('public')->exists($client->$documentType)) {
            return response()->json(['error' => 'Document not found'], 404);
        }
        
        // Generate filename
        $extension = pathinfo($client->$documentType, PATHINFO_EXTENSION);
        $filename = str_replace(' ', '_', "{$client->prenom}_{$client->nom}_{$documentType}.{$extension}");
        
        // Get file path
        $filePath = Storage::disk('public')->path($client->$documentType);
        
        // Return file with proper headers
        return response()->download($filePath, $filename, [
            'Content-Type' => Storage::disk('public')->mimeType($client->$documentType),
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}