<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Progress Steps -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center">
                    <div class="flex items-center {{ $currentStep >= 1 ? 'text-blue-600' : 'text-gray-400' }}">
                        <div class="w-10 h-10 rounded-full border-2 {{ $currentStep >= 1 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300' }} flex items-center justify-center font-semibold">
                            1
                        </div>
                        <span class="ml-2 text-sm font-medium">Fecha y Hora</span>
                    </div>

                    <div class="w-16 h-1 mx-4 {{ $currentStep >= 2 ? 'bg-blue-600' : 'bg-gray-300' }}"></div>

                    <div class="flex items-center {{ $currentStep >= 2 ? 'text-blue-600' : 'text-gray-400' }}">
                        <div class="w-10 h-10 rounded-full border-2 {{ $currentStep >= 2 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300' }} flex items-center justify-center font-semibold">
                            2
                        </div>
                        <span class="ml-2 text-sm font-medium">Tus Datos</span>
                    </div>

                    <div class="w-16 h-1 mx-4 {{ $currentStep >= 3 ? 'bg-blue-600' : 'bg-gray-300' }}"></div>

                    <div class="flex items-center {{ $currentStep >= 3 ? 'text-blue-600' : 'text-gray-400' }}">
                        <div class="w-10 h-10 rounded-full border-2 {{ $currentStep >= 3 ? 'border-blue-600 bg-blue-600 text-white' : 'border-gray-300' }} flex items-center justify-center font-semibold">
                            3
                        </div>
                        <span class="ml-2 text-sm font-medium">Confirmación</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Service Info -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-2">{{ $service->name }}</h2>
            <div class="flex items-center space-x-4 text-sm text-gray-600">
                <span>{{ $service->duration_minutes }} minutos</span>
                <span>•</span>
                <span>${{ number_format($service->price_cents / 100, 0, ',', '.') }} {{ $service->currency }}</span>
            </div>
        </div>

        <!-- Step 1: Calendar and Time Selection -->
        @if($currentStep === 1)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-6">Selecciona fecha y hora</h3>

                <!-- Calendar -->
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <button wire:click="previousMonth" type="button" class="p-2 hover:bg-gray-100 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                            </svg>
                        </button>
                        <h4 class="text-lg font-semibold">{{ $monthName }} {{ $currentYear }}</h4>
                        <button wire:click="nextMonth" type="button" class="p-2 hover:bg-gray-100 rounded-lg">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-2">
                        <div class="text-center text-sm font-medium text-gray-600 py-2">Dom</div>
                        <div class="text-center text-sm font-medium text-gray-600 py-2">Lun</div>
                        <div class="text-center text-sm font-medium text-gray-600 py-2">Mar</div>
                        <div class="text-center text-sm font-medium text-gray-600 py-2">Mié</div>
                        <div class="text-center text-sm font-medium text-gray-600 py-2">Jue</div>
                        <div class="text-center text-sm font-medium text-gray-600 py-2">Vie</div>
                        <div class="text-center text-sm font-medium text-gray-600 py-2">Sáb</div>

                        @for($i = 0; $i < $startDayOfWeek; $i++)
                            <div></div>
                        @endfor

                        @foreach($calendarDays as $day)
                            <button
                                wire:click="selectDate('{{ $day['date'] }}')"
                                type="button"
                                @class([
                                    'p-2 text-center rounded-lg transition-colors',
                                    'bg-blue-600 text-white' => $selectedDate === $day['date'],
                                    'hover:bg-blue-50' => !$day['isPast'] && !$day['isWeekend'] && $selectedDate !== $day['date'],
                                    'text-gray-400 cursor-not-allowed' => $day['isPast'] || $day['isWeekend'],
                                    'border-2 border-blue-600' => $day['isToday'] && $selectedDate !== $day['date'],
                                ])
                                @disabled($day['isPast'] || $day['isWeekend'])
                            >
                                {{ $day['day'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Available Slots -->
                @if($selectedDate && count($availableSlots) > 0)
                    <div>
                        <h4 class="text-md font-semibold text-gray-900 mb-4">Horarios disponibles</h4>
                        <div class="grid grid-cols-3 md:grid-cols-4 gap-3">
                            @foreach($availableSlots as $slot)
                                <button
                                    wire:click="selectTime('{{ $slot['time'] }}')"
                                    type="button"
                                    @class([
                                        'px-4 py-2 text-sm font-medium rounded-lg transition-colors',
                                        'bg-blue-600 text-white' => $selectedTime === $slot['time'],
                                        'bg-white border border-gray-300 text-gray-700 hover:border-blue-300' => $selectedTime !== $slot['time'],
                                    ])
                                >
                                    {{ $slot['formatted'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @elseif($selectedDate)
                    <div class="text-center py-8 text-gray-600">
                        No hay horarios disponibles para esta fecha.
                    </div>
                @endif

                <!-- Actions -->
                <div class="mt-6 flex justify-between">
                    <a href="{{ route('book') }}" class="text-gray-600 hover:text-gray-900">
                        ← Volver
                    </a>
                    <button
                        wire:click="goToStep2"
                        type="button"
                        @disabled(!$selectedDate || !$selectedTime)
                        class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors"
                    >
                        Continuar →
                    </button>
                </div>
            </div>
        @endif

        <!-- Step 2: Client Details -->
        @if($currentStep === 2)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="mb-6">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <p class="text-sm text-blue-900">
                            <strong>Fecha:</strong> {{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }} a las {{ $selectedTime }}
                        </p>
                    </div>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 mb-6">
                    Tus datos
                    @auth
                        <span class="text-sm text-gray-500 font-normal ml-2">(Pre-cargados de tu cuenta)</span>
                    @endauth
                </h3>

                <form wire:submit.prevent="goToStep3" class="space-y-4">
                    <div>
                        <label for="clientName" class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre completo *
                        </label>
                        <input
                            wire:model="clientName"
                            type="text"
                            id="clientName"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        >
                        @error('clientName') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="clientEmail" class="block text-sm font-medium text-gray-700 mb-1">
                            Email *
                        </label>
                        <input
                            wire:model="clientEmail"
                            type="email"
                            id="clientEmail"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            required
                        >
                        @error('clientEmail') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="clientPhone" class="block text-sm font-medium text-gray-700 mb-1">
                            Teléfono
                        </label>
                        <input
                            wire:model="clientPhone"
                            type="tel"
                            id="clientPhone"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        >
                        @error('clientPhone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">
                            Notas adicionales (opcional)
                        </label>
                        <textarea
                            wire:model="notes"
                            id="notes"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                        ></textarea>
                        @error('notes') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                    </div>

                    <div class="flex items-center pt-4">
                        <svg class="w-5 h-5 text-gray-400 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        <span class="text-sm text-gray-600">Tus datos están seguros</span>
                    </div>

                    <div class="mt-6 flex justify-between">
                        <button
                            wire:click="backToStep1"
                            type="button"
                            class="text-gray-600 hover:text-gray-900"
                        >
                            ← Volver
                        </button>
                        <button
                            type="submit"
                            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Agendar Cita
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <!-- Step 3: Confirmation -->
        @if($currentStep === 3 && $booking)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <div class="mb-6">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-2">¡Cita Agendada!</h3>
                    <p class="text-gray-600">Te hemos enviado un email de confirmación</p>
                </div>

                <div class="bg-gray-50 rounded-lg p-6 mb-6 text-left">
                    <h4 class="font-semibold text-gray-900 mb-3">Resumen de tu cita</h4>
                    <div class="space-y-2 text-sm text-gray-600">
                        <p><strong>Servicio:</strong> {{ $service->name }}</p>
                        <p><strong>Fecha:</strong> {{ $booking->scheduled_at->format('d/m/Y') }}</p>
                        <p><strong>Hora:</strong> {{ $booking->scheduled_at->format('H:i') }} - {{ $booking->scheduled_at->copy()->addMinutes($service->duration_minutes)->format('H:i') }}</p>
                        <p><strong>Cliente:</strong> {{ $booking->client_name }}</p>
                    </div>
                </div>

                <div class="space-y-3">
                    <p class="text-sm text-gray-600">
                        Recibirás un recordatorio 24 horas antes de tu cita
                    </p>
                    @auth
                        <a
                            href="{{ route('dashboard') }}"
                            class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Ir al Dashboard
                        </a>
                    @else
                        <a
                            href="{{ route('book') }}"
                            class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                        >
                            Volver a Inicio
                        </a>
                    @endauth
                </div>
            </div>
        @endif
    </div>
</div>
