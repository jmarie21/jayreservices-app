<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class InvoiceManagementController extends Controller
{
    public function index(Request $request)
    {
        $clients = User::where('role', 'client')->get(['id', 'name']);
        $projects = collect();

        if ($request->filled('client_id')) {
            $clientId = $request->client_id;

            if ($request->filled('invoice_id')) {
                // Editing → include invoice projects + available free projects
                $invoice = Invoice::with('projects.service', 'projects.client')->find($request->invoice_id);

                $invoiceProjects = $invoice ? $invoice->projects : collect();

                $freeProjects = Project::with(['service', 'client'])
                    ->where('client_id', $clientId)
                    ->whereDoesntHave('invoices', function ($q) use ($request) {
                        $q->where('invoices.id', '!=', $request->invoice_id);
                    })
                    ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
                    ->when($request->filled('date_to'), fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
                    ->get();

                // Merge invoice projects + free projects
                $projects = $invoiceProjects->merge($freeProjects)->unique('id');
            } else {
                // Creating → only free projects
                $projects = Project::with(['service', 'client'])
                    ->where('client_id', $clientId)
                    ->whereDoesntHave('invoices')
                    ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->date_from))
                    ->when($request->filled('date_to'), fn($q) => $q->whereDate('created_at', '<=', $request->date_to))
                    ->orderBy('created_at', 'desc')
                    ->get();
            }
        }

        $invoices = Invoice::with(['client', 'projects.service', 'projects.client'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('admin/InvoiceManagement', [
            'clients'  => $clients,
            'projects' => $projects,
            'invoices' => $invoices,
        ]);
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'projects' => 'required|array',
            'projects.*' => 'exists:projects,id',
            'paypal_link' => 'nullable|string',
            // 'date_from' => 'date',
            // 'date_to' => 'date|after_or_equal:date_from',
        ]);

        // dd($validated);

        // Generate invoice number
        $invoiceNumber = 'INV-' . strtoupper(Str::random(8));

        // Create invoice with all fields
        $invoice = Invoice::create([
            'client_id' => $request->client_id,
            'invoice_number' => $invoiceNumber,
            'total_amount' => Project::whereIn('id', $request->projects)->sum('total_price'),
            'paypal_link' => $request->paypal_link,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ]);

        // Attach projects to pivot table
        $invoice->projects()->attach($request->projects);

        return redirect()->route('invoice.index')->with('success', 'Invoice created successfully.');
    }

    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'client_id' => 'required|exists:users,id',
            'paypal_link' => 'nullable|string',
            // 'date_from' => 'required|date',
            // 'date_to' => 'required|date|after_or_equal:date_from',
            'projects' => 'required|array',
            'projects.*' => 'exists:projects,id',
        ]);

        $invoice->update([
            'client_id' => $request->client_id,
            'paypal_link' => $request->paypal_link,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
            'total_amount' => Project::whereIn('id', $request->projects)->sum('total_price'),
        ]);

        $invoice->projects()->sync($request->projects);

        return redirect()->route('invoice.index')
            ->with('success', 'Invoice updated successfully.');
    }

}
