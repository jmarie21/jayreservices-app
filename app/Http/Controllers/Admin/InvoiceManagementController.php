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
        // Get all clients
        $clients = User::where('role', 'client')->get(['id', 'name']);

        $projects = collect();

        if ($request->filled('client_id')) {
            $projectsQuery = Project::with(['service', 'client'])
                ->where('client_id', $request->client_id);

            // Check if we are editing an invoice
            $invoiceProjectIds = [];
            if ($request->filled('invoice_id')) {
                $invoice = Invoice::with('projects')->find($request->invoice_id);
                if ($invoice) {
                    $invoiceProjectIds = $invoice->projects->pluck('id')->toArray();
                }
            }

            // Only exclude projects that don't have invoices, but include already attached projects
            $projectsQuery->where(function ($query) use ($invoiceProjectIds) {
                $query->whereDoesntHave('invoices')
                    ->orWhereIn('id', $invoiceProjectIds);
            });

            if ($request->filled('date_from')) {
                $projectsQuery->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $projectsQuery->whereDate('created_at', '<=', $request->date_to);
            }

            $projects = $projectsQuery->orderBy('created_at', 'desc')->get();
        }

        // Fetch all invoices with relations
        $invoices = Invoice::with(['client', 'projects'])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('admin/InvoiceManagement', [
            'clients' => $clients,
            'projects' => $projects,
            'invoices' => $invoices
        ]);
    }



    public function store(Request $request)
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'projects' => 'required|array',
            'projects.*' => 'exists:projects,id',
            'paypal_link' => 'nullable|string',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
        ]);

        dd($validated);

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
            'paypal_link' => 'nullable|url',
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'projects' => 'required|array',
            'projects.*' => 'exists:projects,id',
        ]);

        // Update basic invoice fields
        $invoice->update([
            'client_id' => $request->client_id,
            'paypal_link' => $request->paypal_link,
            'date_from' => $request->date_from,
            'date_to' => $request->date_to,
        ]);

        // Sync projects (will update pivot table)
        $invoice->projects()->sync($request->projects);

        return redirect()->route('invoice.index')
            ->with('success', 'Invoice updated successfully.');
    }

}
