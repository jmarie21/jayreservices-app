<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        .invoice-box { max-width: 800px; margin: auto; padding: 30px; border: 1px solid #eee; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 28px; }
        .company-info, .client-info { margin-bottom: 20px; }
        .client-info strong { display: block; margin-bottom: 4px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .table th, .table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .table th { background-color: #f4f4f4; }
        .totals { margin-top: 20px; text-align: right; }
        .totals h3 { margin: 5px 0; }
        .paypal { margin-top: 30px; text-align: center; }
        .paypal a { background: #0070ba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <h1>Invoice</h1>
            <div>
                <strong>Invoice #:</strong> {{ $invoice->invoice_number }}<br>
                <strong>Date:</strong> {{ $invoice->created_at->format('M d, Y') }}<br>
                <strong>Period:</strong> 
                {{ $invoice->date_from ? $invoice->date_from->format('M d, Y') : '-' }} 
                - 
                {{ $invoice->date_to ? $invoice->date_to->format('M d, Y') : '-' }}
            </div>
        </div>


        <div class="company-info">
            <strong>JayRE Video Editing Services</strong><br>
            jayrealestate98@gmail.com
        </div>

        <div class="client-info">
            <strong>Billed To:</strong>
            {{ $invoice->client->name }}<br>
            {{ $invoice->client->email }}
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->projects as $project)
                    <tr>
                        <td>{{ $project->project_name }}</td>
                        <td>{{ $project->notes ?? '-' }}</td>
                        <td>${{ number_format($project->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <h3>Total: ${{ number_format($invoice->projects->sum('total_price'), 2) }}</h3>
        </div>

        @if($invoice->paypal_link)
        <div class="paypal">
            <a href="{{ $invoice->paypal_link }}" target="_blank">Pay with PayPal</a>
        </div>
        @endif
    </div>
</body>
</html>
