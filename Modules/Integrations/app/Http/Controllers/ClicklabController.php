<?php

namespace Modules\Integrations\Http\Controllers;

use App\Services\ClicklabClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class ClicklabController extends Controller
{
    public function ping(Request $request)
    {
        $client = app(ClicklabClient::class);
        $channel = $request->get('channel', 'sms');
        $to = $request->get('to');
        $text = $request->get('text', 'Prueba Clicklab');

        if (!$to) {
            return response()->json(['success' => false, 'message' => 'Parámetro to requerido'], 422);
        }

        $res = null;
        switch ($channel) {
            case 'whatsapp':
                $res = $client->sendWhatsappText($to, $text);
                break;
            case 'email':
                $res = $client->sendEmail($to, 'Prueba Clicklab', '<b>Prueba Clicklab</b>');
                break;
            default:
                $res = $client->sendSms($to, $text);
        }

        return response()->json(['success' => $res['ok'], 'status' => $res['status'], 'data' => $res['body']]);
    }
}

