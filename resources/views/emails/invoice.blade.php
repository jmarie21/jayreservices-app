

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
    .table th {
        background-color: #f4f4f4;
        color: #0070ba; /* your preferred color */
        font-weight: bold;
    }

    .totals { margin-top: 20px; text-align: right; }
    .totals h3 { margin: 5px 0; }
    .paypal { margin-top: 30px; text-align: center; }
    .paypal a { background: #0070ba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; }
</style>

</head>
<body>
    <div class="invoice-box">
        <div class="header">
            <div style="text-align: center; margin-bottom: 20px;">
                <img src="{{ public_path('jayreblack.png') }}" alt="JayRE Logo" style="height: 200px;">
            </div>

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
                    <th>Service</th>
                    <th>Description</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->projects as $project)
                    @php
                        $style = strtolower($project->style);
                        $format = strtolower($project->format ?? '');
                        $extra = $project->extra_fields ?? [];
                        $effects = $extra['effects'] ?? [];
                        $captions = $extra['captions'] ?? [];
                        $details = [];

                        // =====================
                        //  BASE PRICE
                        // =====================
                        $stylePrices = [
                            'basic video' => ['horizontal' => 40, 'vertical' => 25, 'horizontal and vertical package' => 65],
                            'basic drone only' => ['horizontal' => 25, 'vertical' => 20, 'horizontal and vertical package' => 45],
                            'deluxe video' => ['horizontal' => 60, 'vertical' => 35, 'horizontal and vertical package' => 95],
                            'deluxe drone only' => ['horizontal' => 35, 'vertical' => 30, 'horizontal and vertical package' => 65],
                            'premium video' => ['horizontal' => 80, 'vertical' => 50, 'horizontal and vertical package' => 130],
                            'premium drone only' => ['horizontal' => 45, 'vertical' => 40, 'horizontal and vertical package' => 85],
                            'luxury video' => ['horizontal' => 100, 'vertical' => 70, 'horizontal and vertical package' => 170],
                            'luxury drone only' => ['horizontal' => 60, 'vertical' => 50, 'horizontal and vertical package' => 110],
                        ];

                        $basePrice = $stylePrices[$style][$format] ?? 0;
                        $details[] = "Base {$project->style} ({$project->format}) - \${$basePrice}";

                        // =====================
                        //  ADD-ONS
                        // =====================

                        // Agent
                        if (!empty($project->with_agent)) {
                            $details[] = "With Agent (+$10)";
                        }

                        // Per Property Line
                        if (!empty($project->per_property)) {
                            $details[] = "Per Property Line (+$5)";
                        }

                        // Rush
                        if (!empty($project->rush)) {
                            $details[] = "Rush Order (+$" . 
                                (str_contains($style, 'premium') || str_contains($style, 'luxury') ? "20" : "10") . ")";
                        }

                        // =====================
                        //  CAPTIONS (with pricing)
                        // =====================
                        foreach ($captions as $caption) {
                            $captionPrices = [
                                '3D Text behind the Agent Talking' => 10,
                                '3D Text tracked on the ground etc.' => 15,
                                'Captions while the agent is talking' => 10,
                            ];

                            $captionPrice = $captionPrices[$caption] ?? 0;
                            $details[] = "{$caption} (+\${$captionPrice})";
                        }

                        // =====================
                        //  EFFECTS (with pricing)
                        // =====================
                        foreach ($effects as $effect) {
                            if (is_array($effect)) {
                                $name = $effect['id'];
                                $qty = $effect['quantity'] ?? 1;
                            } else {
                                $name = $effect;
                                $qty = 1;
                            }

                            $effectPrices = [
                                'Painting Transition' => 10,
                                'Earth Zoom Transition' => 15,
                                'Virtual Staging AI' => 20,
                                'Day to Night AI' => 15,
                            ];

                            $price = ($effectPrices[$name] ?? 0) * $qty;
                            $details[] = "{$name} (x{$qty}) (+\${$price})";
                        }

                        // Description HTML
                        $description = implode('<br>', $details);
                    @endphp

                    <tr>
                        <td>{{ Str::limit($project->project_name, 40, '...') }}</td>
                        <td>{{ ucfirst($project->style) }}</td>
                        <td>{!! $description !!}</td>
                        <td>${{ number_format($project->total_price, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>

        </table>

        <div class="totals">
            <h3>Total: ${{ number_format($invoice->projects->sum('total_price'), 2) }}</h3>
        </div>


        <div class="paypal">
            <a href="https://www.paypal.com/paypalme/jmalpas98?country.x=PH&locale.x=en_US&fbclid=IwAR2LRYpasW41135pB8qZ0sf3ahS79rju7XcmgrkTlmxZc7B3Y-M2NrxjAMg" target="_blank">Pay with PayPal</a>
        </div>

    </div>
</body>
</html>
