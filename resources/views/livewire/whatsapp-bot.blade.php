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
                <div class="bg-gray-800 px-4 py-3 border-b border-gray-700">
                    <span class="text-gray-300 font-mono text-sm">Logs del Sistema</span>
                </div>
                <div class="flex-1 p-4 overflow-y-auto font-mono text-xs space-y-2">
                    @foreach($logs as $log)
                        <div class="flex gap-2 border-b border-gray-800 pb-1">
                            <span class="text-gray-500">[{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}]</span>
                            <span class="text-gray-300">{{ $log->message }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>