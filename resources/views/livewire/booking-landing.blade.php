<div class="min-h-screen bg-gray-50">
    <!-- Header Section -->
    <div class="bg-white border-b border-gray-200">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="text-center">
                <h1 class="text-4xl font-bold text-gray-900 mb-4">
                    {{ config('app.name', 'Booking System') }}
                </h1>
                <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                    Agenda tu cita de forma rápida y sencilla
                </p>
            </div>
        </div>
    </div>

    <!-- Services Section -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <h2 class="text-2xl font-semibold text-gray-900 mb-6">
            Selecciona un servicio
        </h2>

        @if($services->isEmpty())
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 text-center">
                <p class="text-gray-600">
                    No hay servicios disponibles en este momento.
                </p>
            </div>
        @else
            <div class="space-y-4">
                @foreach($services as $service)
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:border-blue-300 transition-colors">
                        <div class="p-6">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                        {{ $service->name }}
                                    </h3>

                                    @if($service->description)
                                        <p class="text-gray-600 mb-4">
                                            {{ $service->description }}
                                        </p>
                                    @endif

                                    <div class="flex items-center space-x-4 text-sm text-gray-500">
                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            {{ $service->duration_minutes }} min
                                        </div>

                                        <div class="flex items-center">
                                            <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            @if($service->price_cents === 0)
                                                Gratis
                                            @else
                                                ${{ number_format($service->price_cents / 100, 0, ',', '.') }} {{ $service->currency }}
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <div class="ml-6">
                                    <a href="{{ route('book.wizard', $service) }}"
                                       class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                                        Agendar
                                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Footer Info -->
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="bg-blue-50 rounded-lg border border-blue-200 p-6">
            <h3 class="text-lg font-semibold text-blue-900 mb-3">
                ¿Cómo funciona?
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                            1
                        </div>
                        <h4 class="ml-3 text-sm font-semibold text-blue-900">
                            Elige tu servicio
                        </h4>
                    </div>
                    <p class="text-sm text-blue-700 ml-11">
                        Selecciona el servicio que necesitas
                    </p>
                </div>

                <div>
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                            2
                        </div>
                        <h4 class="ml-3 text-sm font-semibold text-blue-900">
                            Selecciona fecha y hora
                        </h4>
                    </div>
                    <p class="text-sm text-blue-700 ml-11">
                        Escoge el horario que mejor te acomode
                    </p>
                </div>

                <div>
                    <div class="flex items-center mb-2">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-semibold">
                            3
                        </div>
                        <h4 class="ml-3 text-sm font-semibold text-blue-900">
                            Confirma tu reserva
                        </h4>
                    </div>
                    <p class="text-sm text-blue-700 ml-11">
                        Ingresa tus datos y confirma
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
