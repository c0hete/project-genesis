<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Service Seeder
 *
 * Seeds demo services for testing and development.
 * Examples are based on spa/salon use cases.
 */
class ServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = [
            [
                'name' => 'Corte de Cabello',
                'description' => 'Corte de cabello profesional con lavado incluido',
                'duration_minutes' => 45,
                'price_cents' => 1500000, // $15 USD or $15000 CLP
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Manicure',
                'description' => 'Manicure completo con esmaltado tradicional',
                'duration_minutes' => 30,
                'price_cents' => 800000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Pedicure',
                'description' => 'Pedicure completo con exfoliación y masaje',
                'duration_minutes' => 45,
                'price_cents' => 1000000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Masaje Relajante',
                'description' => 'Masaje de cuerpo completo (60 minutos)',
                'duration_minutes' => 60,
                'price_cents' => 2500000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Facial Profundo',
                'description' => 'Limpieza facial profunda con tratamiento hidratante',
                'duration_minutes' => 60,
                'price_cents' => 2000000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Depilación Cejas',
                'description' => 'Perfilado y depilación de cejas',
                'duration_minutes' => 15,
                'price_cents' => 500000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Tinte de Cabello',
                'description' => 'Aplicación de tinte completo con tratamiento',
                'duration_minutes' => 90,
                'price_cents' => 3500000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Alisado Brasileño',
                'description' => 'Tratamiento de alisado permanente',
                'duration_minutes' => 180,
                'price_cents' => 8000000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Extensiones de Pestañas',
                'description' => 'Aplicación de extensiones de pestañas pelo a pelo',
                'duration_minutes' => 120,
                'price_cents' => 4000000,
                'currency' => 'CLP',
                'is_active' => true,
            ],
            [
                'name' => 'Consulta Dental',
                'description' => 'Consulta odontológica general con radiografía',
                'duration_minutes' => 30,
                'price_cents' => 2000000,
                'currency' => 'CLP',
                'is_active' => false, // Example of inactive service
            ],
        ];

        foreach ($services as $serviceData) {
            Service::create($serviceData);
        }

        $this->command->info('Created ' . count($services) . ' services');
    }
}
