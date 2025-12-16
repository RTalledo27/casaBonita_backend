<div style="max-width:680px;margin:0 auto;padding:24px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a;background:#f8fafc;">
  <div style="border-radius:16px 16px 0 0;padding:24px;color:#ffffff;background:linear-gradient(135deg,#1e293b 0%, #334155 100%);text-align:center;">
    <div style="display:inline-block;border:3px solid #fbbf24;border-radius:8px;padding:12px 20px;background-color:rgba(251,191,36,0.1);">
      <div style="color:#ffffff;font-size:20px;font-weight:bold;letter-spacing:2px;">CASA BONITA</div>
    </div>
    <div style="color:#cbd5e1;font-size:11px;letter-spacing:3px;text-transform:uppercase;margin-top:6px;">R E S I D E N C I A L</div>
    <h1 style="color:#ffffff;font-size:24px;font-weight:600;margin-top:16px;">Recordatorio de Pago</h1>
  </div>
  <div style="background:#ffffff;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 16px 16px;padding:24px;box-shadow:0 6px 16px rgba(2,6,23,.06);">
    <div style="line-height:1.7;font-size:16px;margin-bottom:16px;">
      Estimado(a) {{ $client_name ?? 'cliente' }},
      <br><br>
      Le contactamos para recordarle su compromiso de pago del contrato {{ $contract_number ?? 'N/A' }}.
    </div>
    <div style="background:linear-gradient(135deg,#fef3c7 0%, #fde68a 100%);border:2px solid #fbbf24;border-radius:12px;padding:18px;margin:18px 0;box-shadow:0 4px 6px rgba(251,191,36,0.2);">
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <div style="flex:1 1 220px;background:#ffffff;border-radius:8px;padding:12px;">
          <div style="font-size:12px;color:#78716c;font-weight:600;">Cliente</div>
          <div style="font-size:16px;color:#1e293b;font-weight:700;">{{ $client_name ?? '—' }}</div>
        </div>
        <div style="flex:1 1 160px;background:#ffffff;border-radius:8px;padding:12px;">
          <div style="font-size:12px;color:#78716c;font-weight:600;">Vencimiento</div>
          <div style="font-size:16px;color:#1e293b;font-weight:700;">{{ $due_date ?? '—' }}</div>
        </div>
        <div style="flex:1 1 160px;background:#ffffff;border-radius:8px;padding:12px;">
          <div style="font-size:12px;color:#78716c;font-weight:600;">Monto</div>
          <div style="font-size:16px;color:#1e293b;font-weight:700;">S/ {{ number_format($amount ?? 0, 2) }}</div>
        </div>
        <div style="flex:1 1 160px;background:#ffffff;border-radius:8px;padding:12px;">
          <div style="font-size:12px;color:#78716c;font-weight:600;">Contrato</div>
          <div style="font-size:16px;color:#1e293b;font-weight:700;">{{ $contract_number ?? '—' }}</div>
        </div>
      </div>
      @if (!empty($installment_number) || !empty($notes))
        <div style="margin-top:10px;font-size:13px;color:#92400e;text-align:center;">
          @if(!empty($installment_number)) Cuota: <strong>{{ $installment_number }}</strong>@endif
          @if(!empty($notes)) · {{ $notes }}@endif
        </div>
      @endif
    </div>
    @php
      $contactUrl = isset($contact_url) ? $contact_url : null;
      if (!$contactUrl) {
        $wa = env('CONTACT_WHATSAPP');
        $em = env('CONTACT_EMAIL');
        $cu = env('CONTACT_URL');
        if ($cu) {
          $contactUrl = $cu;
        } elseif ($wa) {
          $digits = preg_replace('/[^0-9]/', '', $wa);
          $contactUrl = 'https://wa.me/' . $digits;
        } elseif ($em) {
          $contactUrl = 'mailto:' . $em;
        }
      }
    @endphp
    <div style="text-align:center;margin:10px 0 0 0;">
      @if (!empty($contactUrl))
        <a href="{{ $contactUrl }}" target="_blank" rel="noopener"
           style="display:inline-block;padding:12px 24px;border-radius:8px;color:#1e293b;text-decoration:none;background:linear-gradient(135deg,#fbbf24 0%, #f59e0b 100%);font-weight:600;box-shadow:0 4px 6px rgba(251,191,36,0.3);">
          Contactar
        </a>
      @endif
      @if (!empty($payment_link))
        <a href="{{ $payment_link }}" target="_blank" rel="noopener"
           style="display:inline-block;margin-left:8px;padding:12px 24px;border-radius:8px;color:#ffffff;text-decoration:none;background:#2563eb;font-weight:600;box-shadow:0 4px 6px rgba(37,99,235,.3);">
          Pagar ahora
        </a>
      @endif
    </div>
    <div style="margin:20px 0;height:1px;background:#e5e7eb;"></div>
    <div style="margin-top:14px;color:#64748b;font-size:12px;">
      Este correo fue enviado automáticamente por el sistema de cobranzas. Si no esperabas este mensaje, por favor ignóralo.
    </div>
  </div>
</div>
