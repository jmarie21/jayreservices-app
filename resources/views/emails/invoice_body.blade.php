Hello {{ $user->name }},

Thank you for your business!

Here are the details of your invoice:

Invoice Number: {{ $invoice->invoice_number }}
Amount: {{ number_format($invoice->amount, 2) }} {{ $invoice->currency }}
Due Date: {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : 'N/A' }}

A PDF copy of your invoice is attached to this email.

If you have any questions, feel free to contact us.

Best regards,  
The Invoice Team
