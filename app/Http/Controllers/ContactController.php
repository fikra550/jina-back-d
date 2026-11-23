<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    /**
     * Display a listing of the contacts.
     */
    public function index(): JsonResponse
    {
        $contacts = Contact::latest()->get();
        return response()->json(['contacts' => $contacts]);
    }

    /**
     * Store a newly created contact in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:20',
            'message' => 'required|string|min:10|max:1000'
        ]);

        $contact = Contact::create($validated);

        return response()->json([
            'message' => 'Contact message sent successfully!',
            'contact' => $contact
        ], 201);
    }

    /**
     * Display the specified contact.
     */
    public function show($id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        return response()->json(['contact' => $contact]);
    }

    /**
     * Update the specified contact in storage.
     */
    public function update(Request $request, $id): JsonResponse
    {
        $contact = Contact::findOrFail($id);

        $validated = $request->validate([
            'fullname' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'phone' => 'sometimes|string|max:20',
            'message' => 'sometimes|string|min:10|max:1000'
        ]);

        $contact->update($validated);

        return response()->json([
            'message' => 'Contact updated successfully!',
            'contact' => $contact
        ]);
    }

    /**
     * Remove the specified contact from storage.
     */
    public function destroy($id): JsonResponse
    {
        $contact = Contact::findOrFail($id);
        $contact->delete();

        return response()->json(['message' => 'Contact deleted successfully!']);
    }

    /**
     * Get contacts count for statistics
     */
    public function count(): JsonResponse
    {
        $count = Contact::count();
        return response()->json(['count' => $count]);
    }

    /**
     * Get recent contacts
     */
    public function recent(): JsonResponse
    {
        $recentContacts = Contact::latest()->take(5)->get();
        return response()->json(['recent_contacts' => $recentContacts]);
    }
}