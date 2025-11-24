<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\DB;

class WhatsappBot extends Component
{
    public $status = 'DISCONNECTED';
    public $qrCode = null;
    public $logs = [];

    public function getListeners()
    {
        return ['refreshComponent' => '$refresh'];
    }

    public function render()
    {
        $this->fetchState();
        return view('livewire.whatsapp-bot');
    }

    public function fetchState()
    {
        // CAMBIO CLAVE: Buscamos específicamente el ID 1
        $data = DB::table('whatsapp_status')->find(1);
        
        if ($data) {
            $this->status = $data->status;
            // Solo mostramos QR si estamos en modo conectando
            $this->qrCode = ($data->status === 'CONNECTING') ? $data->qr_code : null;
        } else {
            // Si no hay fila, asumimos desconectado
            $this->status = 'DISCONNECTED';
            $this->qrCode = null;
        }

        $this->logs = DB::table('whatsapp_logs')->orderBy('created_at', 'desc')->limit(15)->get();
    }

    public function startBot()
    {
        exec("pkill -f 'nodejs-bot/bot.js'");
        
        $node = '/usr/local/bin/node';
        if (!file_exists($node)) $node = 'node';

        $script = base_path('nodejs-bot/bot.js');
        $log = base_path('node_debug.log');

        exec("$node \"$script\" > \"$log\" 2>&1 &");

        session()->flash('message', 'Iniciando...');
    }

    public function stopBot()
    {
        exec("pkill -f 'nodejs-bot/bot.js'");
        
        // Actualizamos o creamos el registro ID 1 como desconectado
        DB::table('whatsapp_status')->updateOrInsert(
            ['id' => 1],
            ['status' => 'DISCONNECTED', 'qr_code' => null, 'session_id' => 'default']
        );
        
        $this->log('SYSTEM', 'Motor detenido.');
    }

    public function hardReset()
    {
        exec("pkill -9 -f 'nodejs-bot/bot.js'");
        sleep(1);

        $path = base_path('auth_info_baileys');
        exec("rm -rf \"$path\"");

        // Reset ID 1
        DB::table('whatsapp_status')->updateOrInsert(
            ['id' => 1],
            ['status' => 'DISCONNECTED', 'qr_code' => null, 'session_id' => 'default']
        );
        
        DB::table('whatsapp_logs')->truncate();
        $this->log('SUCCESS', 'Sistema reseteado.');
    }

    private function log($level, $msg) {
        DB::table('whatsapp_logs')->insert([
            'level' => $level, 'message' => $msg, 'created_at' => now(), 'updated_at' => now()
        ]);
    }
}