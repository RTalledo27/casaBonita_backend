<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Aviso de próxima cuota</title>
  <style>
    body { font-family: Arial, sans-serif; color: #111827; }
    .container { max-width: 640px; margin: 0 auto; padding: 24px; }
    .card { border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; }
    .btn { display: inline-block; padding: 10px 16px; border-radius: 8px; background: #2563eb; color: #fff; text-decoration: none; }
    .muted { color: #6b7280; font-size: 12px; }
  </style>
</head>
<body>
  <div class="container">
    <h2>Recordatorio de pago</h2>
    <div class="card">
      <p>Hola {{ $client_name ?? 'cliente' }},</p>
      <p>Te recordamos que tienes una cuota próxima a vencer de tu contrato {{ $contract_number ?? 'N/A' }}.</p>
      <ul>
        <li>Cuota: {{ $installment_number }}{{ isset($notes) && $notes ? ' / ' . $notes : '' }}</li>
        <li>Vencimiento: {{ $due_date }}</li>
        <li>Monto: S/ {{ number_format($amount, 2) }}</li>
        <li>Estado: {{ $status }}</li>
      </ul>
      @if (!empty($payment_link))
        <p>
          <a class="btn" href="{{ $payment_link }}" target="_blank">Pagar ahora</a>
        </p>
      @endif
      <p>Si ya realizaste tu pago, por favor ignora este mensaje.</p>
      <p>Gracias,<br>Equipo de Cobranzas Casa Bonita</p>
    </div>
    <p class="muted">Este correo fue enviado automáticamente por el sistema de cobranzas.</p>
  </div>
</body>
</html>

