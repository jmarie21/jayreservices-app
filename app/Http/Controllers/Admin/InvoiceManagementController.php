<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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
            ->paginate(10)
            ->withQueryString(); // keeps filters & page in URL

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
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
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

    public function view(Invoice $invoice)
    {
        // Load relationships if needed
        $invoice->load(['client', 'projects']);

        // Render PDF view
        $pdf = Pdf::loadView('emails.invoice', compact('invoice'));

        return $pdf->stream("invoice-{$invoice->invoice_number}.pdf");
    }

    public function send(Invoice $invoice)
    {
        $client = $invoice->client;
        $user = Auth::user();

        if (!$client || !$client->email) {
            return back()->withErrors('Client email not found.');
        }

        try {
            // Queue the email
            Mail::to($client->email)->queue(new InvoiceMail($invoice, $user));

            // Update invoice status immediately
            $invoice->update(['status' => 'sent']);

            return back()->with('success', 'Invoice queued for sending successfully.');
        } catch (\Exception $e) {
            Log::error("Failed to queue invoice #{$invoice->invoice_number}: " . $e->getMessage());
            return back()->withErrors("Failed to queue invoice. Error: {$e->getMessage()}");
        }
    }

    
    public function markAsPaid($id)
    {
        $invoice = Invoice::findOrFail($id);

        // Only allow marking as paid if it was sent
        if ($invoice->status === 'sent') {
            $invoice->status = 'paid';
            $invoice->save();

            return redirect()->back()->with('success', 'Invoice marked as paid.');
        }

        return redirect()->back()->with('error', 'Only sent invoices can be marked as paid.');
    }

    public function cancel($id)
    {
        $invoice = Invoice::findOrFail($id);

        if (!in_array($invoice->status, ['paid', 'cancelled'])) {
            // Detach all projects linked to this invoice
            $invoice->projects()->detach();

            $invoice->status = 'cancelled';
            $invoice->save();
            
            return redirect()->back()->with('success', 'Invoice cancelled');
        }

        return redirect()->back()->with('error', 'Error cancelling invoice');
    }

}
