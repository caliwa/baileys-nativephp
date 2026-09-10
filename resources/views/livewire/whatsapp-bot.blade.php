<div wire:poll.1s="fetchState">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>

    <div class="p-6 max-w-6xl mx-auto bg-gray-50 min-h-screen flex flex-col md:flex-row gap-6">
        
        <div class="w-full md:w-1/3 space-y-6">
            
            <div class="bg-white p-6 rounded-xl shadow-md text-center">
                <h2 class="text-lg font-bold text-gray-700 mb-4">Estado del Motor</h2>
                
                @if($status === 'CONNECTED')
                    <div class="inline-block p-4 rounded-full bg-green-100 mb-4">
                        <span class="text-4xl">📱</span>
                    </div>
                    <h3 class="text-2xl font-bold text-green-600">CONECTADO</h3>
                
                @elseif($status === 'CONNECTING')
                    <div class="inline-block p-4 rounded-full bg-yellow-100 mb-4 animate-pulse">
                        <span class="text-4xl">🔗</span>
                    </div>
                    <h3 class="text-xl font-bold text-yellow-600">VINCULANDO...</h3>
                    
                    @if($qrCode)
                        <div class="mt-4 bg-white p-2 border-2 border-gray-200 rounded-lg inline-block"
                             x-data="{ qr: @entangle('qrCode') }"
                             x-init="$watch('qr', value => {
                                if(value) {
                                    new QRious({
                                        element: $refs.canvas,
                                        value: value,
                                        size: 200
                                    });
                                }
                             })"
                             x-effect="if(qr) new QRious({ element: $refs.canvas, value: qr, size: 200 })">
                            
                            <canvas x-ref="canvas"></canvas>
                        </div>
                        
                        <p class="text-xs font-bold text-red-500 mt-2 animate-bounce">¡ESCANEA RÁPIDO!</p>
                        
                        <div class="mt-2">
                            <p class="text-[10px] text-gray-400">Data recibida:</p>
                            <textarea readonly class="w-full h-8 text-[8px] border text-gray-300 resize-none">{{ substr($qrCode, 0, 30) }}...</textarea>
                        </div>
                    @else
                        <p class="text-sm text-gray-400 mt-4">Esperando código...</p>
                    @endif

                @else
                    <div class="inline-block p-4 rounded-full bg-gray-100 mb-4">
                        <span class="text-4xl">💤</span>
                    </div>
                    <h3 class="text-xl font-bold text-gray-500">APAGADO</h3>
                @endif
            </div>

            <div class="bg-white p-6 rounded-xl shadow-md space-y-3">
                @if($status === 'DISCONNECTED')
                    <button wire:click="startBot" class="w-full py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-lg">
                        ▶️ INICIAR MOTOR
                    </button>
                @else
                    <button wire:click="stopBot" class="w-full py-3 bg-gray-600 hover:bg-gray-700 text-white font-bold rounded-lg">
                        ⏸️ DETENER
                    </button>
                @endif
                
                <hr class="border-gray-200 my-4">
                <button wire:click="hardReset" class="w-full py-2 bg-red-100 hover:bg-red-200 text-red-600 font-bold rounded-lg text-sm">
                    ☢️ RESETEO DE FÁBRICA
                </button>
            </div>
        </div>

        <div class="w-full md:w-2/3">
            <div class="bg-gray-900 rounded-xl shadow-lg overflow-hidden h-[600px] flex flex-col">
                <div class="bg-gray-800 px-4 py-3 border-b border-gray-700 flex justify-between items-center">
                    <span class="text-gray-300 font-mono text-sm">Registro de Actividad (Logs & Mensajes)</span>
                    <span class="text-xs bg-gray-700 text-gray-300 px-2 py-1 rounded">Auto-refresh</span>
                </div>
                <div class="flex-1 p-4 overflow-y-auto font-mono text-sm space-y-2">
                    @forelse($logs as $log)
                        <div class="flex gap-3 border-b border-gray-800 pb-2 p-2 rounded hover:bg-gray-800 transition-colors">
                            <span class="text-gray-500 shrink-0">[{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}]</span>
                            
                            @if($log->level === 'MESSAGE_IN')
                                <span class="text-green-400 font-bold shrink-0">💬 {{ $log->phone ?? 'Desconocido' }}:</span>
                                <span class="text-gray-100 break-words">{{ $log->message }}</span>
                            @elseif($log->level === 'INFO')
                                <span class="text-blue-400 font-bold shrink-0">[INFO]</span>
                                <span class="text-blue-200">{{ $log->message }}</span>
                            @elseif($log->level === 'WARN')
                                <span class="text-yellow-400 font-bold shrink-0">[WARN]</span>
                                <span class="text-yellow-200">{{ $log->message }}</span>
                            @elseif($log->level === 'ERROR')
                                <span class="text-red-500 font-bold shrink-0">[ERROR]</span>
                                <span class="text-red-200">{{ $log->message }}</span>
                            @elseif($log->level === 'SUCCESS')
                                <span class="text-emerald-400 font-bold shrink-0">[SUCCESS]</span>
                                <span class="text-emerald-200">{{ $log->message }}</span>
                            @else
                                <span class="text-purple-400 font-bold shrink-0">[{{ $log->level }}]</span>
                                <span class="text-gray-300">{{ $log->message }}</span>
                            @endif
                        </div>
                    @empty
                        <div class="text-center text-gray-500 mt-10">No hay actividad reciente.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>