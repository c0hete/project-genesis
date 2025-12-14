# Plan de Implementación de Vistas - Project Genesis

**Versión:** 1.0
**Fecha:** 2025-12-14
**Stack:** Laravel 12 + Jetstream + Livewire + Blade

---

## Contexto

El backend de Project Genesis está completo con:
- ✅ Modelos: Service, Booking, User
- ✅ Servicios: AvailabilityGenerator, HubEventReporter, MetricsCollector, PaymentGatewayFactory
- ✅ 8 estados de booking lifecycle
- ✅ Multi-gateway payment system
- ✅ Commands: SendReminders, MarkNoShows, CheckOutdatedPackages
- ✅ Tests: 23/23 passing
- ✅ Seeders con datos demo

**Falta:** Vistas y rutas para que usuarios puedan interactuar con el sistema.

---

## Filosofía de Diseño

### Stack Decision
**Usaremos: Laravel Blade + Livewire** (NO Inertia/Vue)

**Razones:**
1. Es más vanilla Laravel (objetivo del proyecto)
2. Livewire ya viene con Jetstream
3. Menos JavaScript, más server-side
4. Más fácil de forkear y personalizar
5. Consistente con la arquitectura actual

### Principios UX (Inspirado en Calendly, Acuity, SimplyBook.me)

**Para Clientes (Prioridad #1):**
- ✅ **Flujo de 3 pasos máximo:** Servicio → Fecha/Hora → Confirmar
- ✅ **Sin fricción:** No login obligatorio hasta confirmar
- ✅ **Visual first:** Ver disponibilidad antes de llenar formularios
- ✅ **Mobile-first:** 70% de reservas son desde móvil
- ✅ **Confirmación clara:** Email + SMS + iCal attachment
- ✅ **Self-service:** Cancelar/reagendar sin llamar

**Para Profesionales (Admin/Staff):**
- ✅ **Dashboard simple:** Métricas clave + próximas citas
- ✅ **Calendario visual:** Ver semana/mes de un vistazo
- ✅ **Notificaciones:** Nuevas reservas, cancelaciones
- ✅ **Quick actions:** Confirmar/cancelar con 1 click
- ✅ **Notas privadas:** Por cliente y por cita

**Casos de Uso Reales:**
- 🏗️ **Arquitecta:** Consultas de 1-2 horas, seguimiento de proyectos
- 🧠 **Psicóloga:** Sesiones de 45-60 min, pacientes recurrentes
- 🦷 **Dentista:** Múltiples servicios (limpieza, extracción, etc.), duración variable
- 💇 **Estilista:** Servicios rápidos (30-90 min), alta rotación
- 🧘 **Coach/Mentor:** Sesiones virtuales o presenciales

### Principios Técnicos
- ✅ Simple y funcional primero
- ✅ Usar Tailwind CSS (ya incluido)
- ✅ Componentes Livewire para interactividad
- ✅ Blade para layouts estáticos
- ✅ Sin bibliotecas JavaScript adicionales
- ✅ Mobile-first responsive design

---

## Arquitectura de Vistas

### Estructura de Directorios

```
resources/
├── views/
│   ├── layouts/
│   │   └── app.blade.php           # Layout principal (Jetstream)
│   ├── components/
│   │   ├── booking-card.blade.php  # Tarjeta de booking
│   │   ├── service-card.blade.php  # Tarjeta de servicio
│   │   └── status-badge.blade.php  # Badge de estado
│   ├── livewire/
│   │   ├── bookings/
│   │   │   ├── calendar.blade.php       # Vista calendario
│   │   │   ├── booking-list.blade.php   # Lista de bookings
│   │   │   ├── booking-form.blade.php   # Crear/editar
│   │   │   └── booking-detail.blade.php # Detalle
│   │   ├── services/
│   │   │   ├── service-list.blade.php   # Lista de servicios
│   │   │   └── service-form.blade.php   # Crear/editar
│   │   └── dashboard/
│   │       └── admin-dashboard.blade.php # Dashboard admin
│   ├── bookings/
│   │   └── index.blade.php         # Page wrapper
│   ├── services/
│   │   └── index.blade.php         # Page wrapper
│   └── dashboard.blade.php         # Dashboard (ya existe)
│
└── app/
    └── Http/
        └── Livewire/
            ├── Bookings/
            │   ├── Calendar.php
            │   ├── BookingList.php
            │   ├── BookingForm.php
            │   └── BookingDetail.php
            ├── Services/
            │   ├── ServiceList.php
            │   └── ServiceForm.php
            └── Dashboard/
                └── AdminDashboard.php
```

---

## Funcionalidades por Vista

### 1. Dashboard Admin (Ya implementado parcialmente)

**Ruta:** `/dashboard`
**Controller:** `DashboardController` (ya existe)
**Vista:** Livewire component

**Métricas a mostrar:**
- Revenue Today (desde MetricsCollector)
- Bookings Today (created, confirmed)
- Upcoming 24h
- Completion Rate
- Booking trends (7 días)
- Popular services
- Recent activity feed

**Acciones:**
- Ver detalles de booking
- Quick actions (confirm, cancel, start)

---

### 2. Gestión de Servicios

#### 2.1 Lista de Servicios (`/services`)

**Componente:** `ServiceList.php`
**Usuarios:** Admin, Staff

**Funcionalidades:**
- Ver todos los servicios (activos e inactivos)
- Filtrar por estado (active/inactive)
- Buscar por nombre
- Ver precio, duración
- Toggle active/inactive
- Botón "Crear Nuevo Servicio"
- Editar servicio
- Eliminar servicio (soft delete opcional)

**Datos a mostrar por servicio:**
- Nombre
- Descripción
- Duración (min)
- Precio (formatted)
- Estado (active badge)
- Acciones (edit, toggle)

#### 2.2 Formulario de Servicio (`/services/create`, `/services/{id}/edit`)

**Componente:** `ServiceForm.php`
**Usuarios:** Admin

**Campos:**
- name (required, max:255)
- description (optional, textarea)
- duration_minutes (required, number, min:15, max:480)
- price_cents (required, number, min:0)
- currency (select: CLP, USD, MXN, ARS, EUR)
- is_active (checkbox, default true)

**Validaciones:**
- name único
- duration múltiplo de 15 min
- price >= 0

---

### 3. Gestión de Bookings

#### 3.1 Lista de Bookings (`/bookings`)

**Componente:** `BookingList.php`
**Usuarios:** Admin, Staff, Client (solo sus bookings)

**Funcionalidades:**
- Ver bookings según rol:
  - Admin/Staff: todos
  - Client: solo propios
- Filtrar por estado (8 estados)
- Filtrar por fecha (today, upcoming, past, custom range)
- Filtrar por servicio
- Buscar por cliente
- Paginación (20 por página)
- Acciones rápidas según estado

**Columnas:**
- ID (short UUID)
- Cliente
- Servicio
- Fecha/Hora programada
- Estado (badge con color)
- Monto
- Staff asignado
- Acciones

**Acciones rápidas:**
- created → Confirm (trigger payment), Cancel
- confirmed → Start, Cancel, Reschedule
- reminded → Start, Cancel
- started → Complete
- (otros estados son finales o solo view)

#### 3.2 Calendario de Bookings (`/bookings/calendar`)

**Componente:** `Calendar.php`
**Usuarios:** Admin, Staff

**Funcionalidades:**
- Vista mensual con bookings
- Color-coded por estado
- Click en día → ver bookings del día
- Click en slot vacío → crear booking
- Navegación mes anterior/siguiente
- Vista semanal (opcional)

**Integración:**
- Usa AvailabilityGenerator para mostrar slots disponibles
- Muestra bookings existentes
- Permite drag-and-drop para reschedule (v2)

#### 3.3 Detalle de Booking (`/bookings/{id}`)

**Componente:** `BookingDetail.php`
**Usuarios:** Admin, Staff, Client (solo propio)

**Información mostrada:**
- Datos del cliente (name, email, phone)
- Servicio (name, duration, price)
- Estado actual + historial de estados
- Fecha/hora programada
- Staff asignado
- Notas del cliente
- Notas internas del staff
- Payment status
- Timestamps (created, confirmed, reminded, started, completed)

**Acciones disponibles (según estado):**
- Confirm → Trigger payment
- Cancel → Cancellation flow
- Reschedule → Select new date/time
- Start → Mark as started
- Complete → Mark as completed + actual duration
- Mark No-Show

#### 3.4 Formulario de Booking (`/bookings/create`)

**Componente:** `BookingForm.php`
**Usuarios:** Admin, Staff, Client

**Flujo:**
1. Seleccionar servicio (dropdown)
2. Ver disponibilidad (Calendar component integrado)
3. Seleccionar slot
4. Ingresar datos cliente (si Admin/Staff creating for someone)
5. Notas opcionales
6. Confirmar → Redirect a payment

**Campos:**
- service_id (required, select)
- scheduled_at (required, datetime picker)
- client_name (required if guest/admin creating)
- client_email (required)
- client_phone (optional)
- notes (optional, textarea)
- assigned_to (optional, staff select - solo Admin)

---

### 4. Vista Pública (Cliente) - PRIORIDAD MÁXIMA

#### 4.1 Landing Page del Profesional (`/{username}` o `/book`)

**Inspiración:** Calendly booking page
**Componente:** `BookingLanding.php` (Livewire)
**Usuarios:** Guest (público)
**NO requiere login**

**Estructura de la página:**

```
┌─────────────────────────────────────────┐
│  [Logo/Foto] Dra. María González       │
│  Psicóloga Clínica                      │
│  ⭐⭐⭐⭐⭐ (4.9) · 127 sesiones         │
│                                         │
│  "Terapia individual y familiar"        │
│  📍 Online o Presencial                 │
│  🕐 Lun-Vie 9:00-18:00                  │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  Selecciona un servicio:                │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ 🧠 Sesión Individual              │ │
│  │ 60 minutos · $45.000              │ │
│  │ [Agendar →]                       │ │
│  └───────────────────────────────────┘ │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ 👨‍👩‍👧 Terapia Familiar              │ │
│  │ 90 minutos · $65.000              │ │
│  │ [Agendar →]                       │ │
│  └───────────────────────────────────┘ │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │ 📞 Primera Consulta (Gratis)      │ │
│  │ 30 minutos · Gratis               │ │
│  │ [Agendar →]                       │ │
│  └───────────────────────────────────┘ │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  Testimonios / FAQ / Políticas          │
└─────────────────────────────────────────┘
```

**Features:**
- Foto y bio del profesional
- Rating y reseñas (v2)
- Servicios con descripción clara
- Precios visibles
- Duración visible
- Sin login requerido (guest flow)

---

#### 4.2 Flujo de Reserva Estilo Calendly (3 Pasos)

**Ruta:** `/book/{service}`
**Componente:** `BookingWizard.php` (Livewire multi-step)

##### **PASO 1: Seleccionar Fecha y Hora** (Sin login)

```
┌─────────────────────────────────────────┐
│  ← Volver  |  Sesión Individual (60min) │
│                                         │
│  ┌─────────────┐  ┌─────────────────┐  │
│  │   ENERO     │  │                 │  │
│  │  L  M  X  J │  │  Miércoles 15   │  │
│  │  1  2  3  4 │  │  Enero 2025     │  │
│  │  8  9 10 11 │  │                 │  │
│  │ 15[16]17 18 │  │  ☀️ Mañana      │  │
│  │ 22 23 24 25 │  │  ⚪ 09:00        │  │
│  │ 29 30 31    │  │  ⚪ 09:30        │  │
│  │             │  │  ⚪ 10:00        │  │
│  │  → Feb      │  │  ⚪ 10:30        │  │
│  └─────────────┘  │  ⚪ 11:00        │  │
│                   │                 │  │
│                   │  🌙 Tarde        │  │
│                   │  ⚪ 14:00        │  │
│                   │  ⚪ 14:30        │  │
│                   │  ⚪ 15:00 ✓      │  │
│                   │  ⚪ 15:30        │  │
│                   │  ⚪ 16:00        │  │
│                   │  ⚪ 16:30        │  │
│                   └─────────────────┘  │
│                                         │
│              [Continuar →]              │
└─────────────────────────────────────────┘
```

**Funcionalidad:**
- Ver calendario mensual
- Solo días con disponibilidad son clickeables
- Click en día → muestra slots de ese día
- Slots agrupados por mañana/tarde
- Slots ocupados deshabilitados
- Timezone awareness (opcional)
- Mobile: swipe entre días

**Backend:**
- Usa `AvailabilityGenerator->getSlotsForDate()`
- Verifica bookings existentes
- Filtra slots ocupados
- Cache de 5 min

---

##### **PASO 2: Tus Datos** (Guest flow)

```
┌─────────────────────────────────────────┐
│  ← Volver                               │
│                                         │
│  📅 Miércoles 15 Enero, 15:00          │
│  🧠 Sesión Individual (60 min)         │
│  💰 $45.000                             │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  Tus datos:                             │
│                                         │
│  Nombre completo *                      │
│  ┌─────────────────────────────────┐   │
│  │ María José Pérez                │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Email *                                │
│  ┌─────────────────────────────────┐   │
│  │ mariajose@gmail.com             │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Teléfono                               │
│  ┌─────────────────────────────────┐   │
│  │ +56 9 1234 5678                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Motivo de consulta (opcional)          │
│  ┌─────────────────────────────────┐   │
│  │ Ansiedad y manejo del estrés    │   │
│  │                                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  □ He leído la política de              │
│    cancelación                          │
│                                         │
│              [Agendar Cita]             │
│                                         │
│  🔒 Tus datos están seguros             │
└─────────────────────────────────────────┘
```

**Funcionalidad:**
- Formulario simple (solo 3-4 campos)
- Email es único, crea cuenta automáticamente si no existe
- Validación en tiempo real (Livewire)
- Resumen visible de lo seleccionado
- Sin password en este paso (se envía después)
- Checkbox políticas/términos

**Backend:**
- Crea User si no existe (por email)
- Genera password temporal
- Envía email de bienvenida
- Crea Booking con status: CREATED

---

##### **PASO 3: Confirmación** (Depende de config)

**Opción A: Pago Inmediato (Gateway activo)**

```
┌─────────────────────────────────────────┐
│  ✓ Cita Agendada                        │
│                                         │
│  📅 Miércoles 15 Enero, 15:00          │
│  🧠 Sesión Individual                   │
│  👤 María José Pérez                    │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  💳 Confirma tu reserva con pago        │
│                                         │
│  Total: $45.000 CLP                     │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ [💳 Pagar con Webpay]           │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ [💳 Pagar con MercadoPago]      │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Puedes cancelar gratis hasta 24h       │
│  antes de tu cita                       │
│                                         │
│  Recibirás recordatorios por email      │
└─────────────────────────────────────────┘
```

**Opción B: Confirmación Manual (Sin gateway o pago en persona)**

```
┌─────────────────────────────────────────┐
│  ✅ ¡Cita Solicitada!                   │
│                                         │
│  📅 Miércoles 15 Enero, 15:00          │
│  🧠 Sesión Individual                   │
│  👤 María José Pérez                    │
│  💰 $45.000 (Pago en consulta)         │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  ✉️ Te enviamos un email a:             │
│  mariajose@gmail.com                    │
│                                         │
│  La profesional confirmará tu cita      │
│  en las próximas horas.                 │
│                                         │
│  📧 Revisa tu email para:               │
│  • Link de confirmación                 │
│  • Agregar a tu calendario              │
│  • Instrucciones de acceso              │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ [Ver Mis Citas]                 │   │
│  └─────────────────────────────────┘   │
│                                         │
│  🔗 Link de tu cita:                    │
│  bookingsystem.com/b/abc123             │
│  (Guárdalo para reagendar/cancelar)    │
└─────────────────────────────────────────┘
```

**Funcionalidad:**
- Confirmación visual clara
- Email de confirmación automático
- iCal attachment para agregar a calendario
- Link único para gestionar la cita
- Instrucciones claras siguiente paso
- Opción de crear password (login posterior)

**Emails enviados:**
1. **Al cliente:** Confirmación + iCal + link gestión
2. **Al profesional:** Nueva reserva pendiente

---

#### 4.3 Gestión de Cita (Self-Service)

**Ruta:** `/bookings/{uuid}/manage?token={secret}`
**No requiere login** (usa token secreto en URL)

```
┌─────────────────────────────────────────┐
│  Tu Cita con Dra. María González        │
│                                         │
│  Estado: ⏳ Pendiente Confirmación      │
│                                         │
│  📅 Miércoles 15 Enero 2025             │
│  🕐 15:00 - 16:00                       │
│  🧠 Sesión Individual                   │
│  📍 Online (link se enviará 1h antes)  │
│                                         │
│  ─────────────────────────────────────  │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ 📅 Agregar a Calendario         │   │
│  │ (Google / Outlook / iCal)       │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ 🔄 Reagendar Cita               │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ ❌ Cancelar Cita                │   │
│  │ (Gratis hasta 24h antes)        │   │
│  └─────────────────────────────────┘   │
│                                         │
│  💬 ¿Necesitas ayuda?                   │
│  contacto@ejemplo.com                   │
└─────────────────────────────────────────┘
```

**Funcionalidad:**
- Ver detalles de la cita
- Agregar a calendario (iCal download)
- Reagendar (vuelve al wizard, paso 1)
- Cancelar con confirmación
- Sin login requerido (token en URL)

---

#### 4.4 Portal del Cliente (Opcional, con login)

**Ruta:** `/my-bookings`
**Requiere:** Login

```
┌─────────────────────────────────────────┐
│  Mis Citas                              │
│                                         │
│  Próximas (2)                           │
│  ┌─────────────────────────────────┐   │
│  │ 📅 Mié 15 Ene, 15:00            │   │
│  │ Sesión Individual · Confirmada  │   │
│  │ [Ver] [Reagendar] [Cancelar]    │   │
│  └─────────────────────────────────┘   │
│                                         │
│  ┌─────────────────────────────────┐   │
│  │ 📅 Lun 20 Ene, 10:00            │   │
│  │ Primera Consulta · Pendiente    │   │
│  │ [Ver] [Reagendar] [Cancelar]    │   │
│  └─────────────────────────────────┘   │
│                                         │
│  Pasadas (5)                            │
│  ┌─────────────────────────────────┐   │
│  │ 📅 Lun 8 Ene, 14:00             │   │
│  │ Sesión Individual · Completada  │   │
│  │ [Ver Recibo]                    │   │
│  └─────────────────────────────────┘   │
│                                         │
│  [+ Agendar Nueva Cita]                 │
└─────────────────────────────────────────┘
```

---

## Rutas Necesarias

```php
// routes/web.php

use App\Http\Livewire\Bookings\Calendar;
use App\Http\Livewire\Bookings\BookingList;
use App\Http\Livewire\Bookings\BookingForm;
use App\Http\Livewire\Bookings\BookingDetail;
use App\Http\Livewire\Services\ServiceList;
use App\Http\Livewire\Services\ServiceForm;
use App\Http\Livewire\Dashboard\AdminDashboard;

// Public routes
Route::get('/services/public', [PublicController::class, 'services'])->name('services.public');

// Authenticated routes
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])->group(function () {

    // Dashboard (existing)
    Route::get('/dashboard', AdminDashboard::class)->name('dashboard');

    // Services Management (Admin/Staff only)
    Route::middleware(['can:manage-services'])->group(function () {
        Route::get('/services', ServiceList::class)->name('services.index');
        Route::get('/services/create', ServiceForm::class)->name('services.create');
        Route::get('/services/{service}/edit', ServiceForm::class)->name('services.edit');
    });

    // Bookings Management
    Route::prefix('bookings')->name('bookings.')->group(function () {
        Route::get('/', BookingList::class)->name('index');
        Route::get('/calendar', Calendar::class)->name('calendar');
        Route::get('/create', BookingForm::class)->name('create');
        Route::get('/{booking}', BookingDetail::class)->name('show');
        Route::get('/{booking}/edit', BookingForm::class)->name('edit');
    });
});

// API routes for AJAX (optional, for future)
Route::prefix('api/v1')->middleware('auth:sanctum')->group(function () {
    Route::get('/availability/{service}/{date}', [AvailabilityController::class, 'getSlots']);
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/{booking}/start', [BookingController::class, 'start']);
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);
});
```

---

## Controllers Necesarios

### 1. `PublicController.php`
- `services()` - Mostrar servicios públicos

### 2. `AvailabilityController.php` (API)
- `getSlots(Service $service, $date)` - Return JSON slots

### 3. `BookingController.php` (API)
- `confirm(Booking $booking)` - Trigger payment
- `cancel(Booking $booking)` - Cancel booking
- `start(Booking $booking)` - Start service
- `complete(Booking $booking)` - Complete service

---

## Componentes Livewire a Crear

### Priority 1 (Esenciales)
1. ✅ `Dashboard\AdminDashboard` - Dashboard metrics
2. ⏳ `Services\ServiceList` - CRUD services
3. ⏳ `Services\ServiceForm` - Create/Edit service
4. ⏳ `Bookings\BookingList` - List bookings
5. ⏳ `Bookings\BookingForm` - Create/Edit booking
6. ⏳ `Bookings\BookingDetail` - View booking detail

### Priority 2 (Importantes)
7. ⏳ `Bookings\Calendar` - Calendar view
8. ⏳ Components para quick actions (confirm, cancel, etc.)

### Priority 3 (Nice to have)
9. ⏳ Real-time notifications (Livewire events)
10. ⏳ Search/Filter components

---

## Orden de Implementación (ACTUALIZADO - Client-First)

### ✅ Ya Implementado (Reutilizar)
- ✅ Modelo Service (UUID, duration, price)
- ✅ Modelo Booking (8 estados, auto events)
- ✅ AvailabilityGenerator (slots calculator)
- ✅ PaymentGatewayFactory (5 gateways)
- ✅ HubEventReporter (integrado en Booking)
- ✅ MetricsCollector (para dashboard)
- ✅ Commands (SendReminders, MarkNoShows)
- ✅ Seeders (datos demo)
- ✅ Tests (23/23 passing)

### Fase 1: Flujo Público del Cliente (PRIORIDAD MÁXIMA)
**Objetivo:** Que un cliente pueda reservar sin fricción

1. **Landing Page Pública** (`/book`)
   - Componente Livewire: `BookingLanding.php`
   - Vista Blade simple con servicios activos
   - Botón "Agendar" por servicio
   - Reutiliza: Service model (query active)

2. **Wizard de Reserva (3 pasos)** (`/book/{service}`)
   - Componente Livewire: `BookingWizard.php` (multi-step)
   - Paso 1: Calendario + Slots (usa AvailabilityGenerator ✅)
   - Paso 2: Datos del cliente (form simple)
   - Paso 3: Confirmación + Pago opcional
   - Reutiliza: AvailabilityGenerator, PaymentGatewayFactory

3. **Página de Confirmación**
   - Email automático con iCal
   - Link mágico para gestionar cita
   - Reutiliza: Booking model, events automáticos

4. **Gestión de Cita** (`/bookings/{uuid}/manage?token=xxx`)
   - Ver detalles
   - Reagendar (vuelve a wizard)
   - Cancelar
   - Sin login requerido (token-based)

### Fase 2: Portal del Profesional (Admin/Staff)
**Objetivo:** Gestionar citas y clientes

1. **Dashboard Admin**
   - Reutilizar: DashboardController ya existe ✅
   - Actualizar: Vista Livewire con métricas
   - Reutiliza: MetricsCollector ✅

2. **Lista de Bookings** (`/admin/bookings`)
   - Componente: `BookingList.php`
   - Filtros por estado, fecha, servicio
   - Quick actions
   - Reutiliza: Booking model con scopes

3. **Calendario Vista Profesional** (`/admin/calendar`)
   - Componente: `Calendar.php`
   - Vista semanal/mensual
   - Reutiliza: AvailabilityGenerator ✅

4. **Gestión de Servicios** (`/admin/services`)
   - Componente: `ServiceList.php` + `ServiceForm.php`
   - CRUD completo
   - Reutiliza: Service model

### Fase 3: Integraciones y Mejoras
1. Webhook handlers para payment gateways
2. Notificaciones email (usa Command SendReminders ✅)
3. Portal del cliente con login (`/my-bookings`)

### Fase 4: Testing y Deploy
1. Tests feature para flujo completo
2. Test manual end-to-end
3. Documentación de deployment

---

## Gates y Policies

```php
// app/Providers/AuthServiceProvider.php

Gate::define('manage-services', function (User $user) {
    return $user->isAdmin() || $user->isStaff();
});

Gate::define('manage-all-bookings', function (User $user) {
    return $user->isAdmin() || $user->isStaff();
});

Gate::define('view-booking', function (User $user, Booking $booking) {
    return $user->isAdmin()
        || $user->isStaff()
        || $booking->user_id === $user->id;
});
```

---

## Integración con Backend Existente

### AvailabilityGenerator
- Usar en Calendar component
- Usar en BookingForm para mostrar slots disponibles
- Endpoint API: `/api/v1/availability/{service}/{date}`

### PaymentGatewayFactory
- Trigger en `BookingController@confirm`
- Redirect a gateway según `.env`
- Webhook handler para actualizar status

### HubEventReporter
- Ya está integrado en modelo Booking
- Events se reportan automáticamente en transiciones

### MetricsCollector
- Usar en AdminDashboard
- Mostrar métricas en tiempo real

---

## Testing Strategy

### Manual Testing Checklist
- [ ] Admin puede crear servicio
- [ ] Admin puede editar servicio
- [ ] Admin puede toggle active/inactive
- [ ] Cliente puede ver servicios públicos
- [ ] Cliente puede crear booking
- [ ] Sistema muestra slots disponibles correctamente
- [ ] Admin puede confirmar booking
- [ ] Admin puede cancelar booking
- [ ] Admin puede marcar started
- [ ] Admin puede marcar completed
- [ ] Dashboard muestra métricas correctas
- [ ] Calendario muestra bookings correctamente

### Feature Tests (Future)
- `ServiceManagementTest.php`
- `BookingManagementTest.php`
- `AvailabilityTest.php`
- `PaymentFlowTest.php`

---

## Diseño UI/UX

### Paleta de Colores (Tailwind)
- Primary: blue-600
- Success: green-600
- Warning: yellow-500
- Danger: red-600
- Gray scale para backgrounds

### Booking Status Colors
```php
// En Booking model
public function getStatusColorAttribute(): string
{
    return match($this->status) {
        BookingStatus::CREATED => 'gray',
        BookingStatus::CONFIRMED => 'blue',
        BookingStatus::REMINDED => 'purple',
        BookingStatus::STARTED => 'yellow',
        BookingStatus::COMPLETED => 'green',
        BookingStatus::CANCELLED => 'red',
        BookingStatus::NO_SHOW => 'orange',
        BookingStatus::RESCHEDULED => 'indigo',
    };
}
```

---

## Próximos Pasos Inmediatos

1. **Crear estructura base de directorios**
2. **Implementar ServiceList + ServiceForm** (más simple, para testear patrón)
3. **Implementar BookingList + BookingForm**
4. **Integrar AvailabilityGenerator en formulario**
5. **Testear flujo completo: Crear servicio → Crear booking → Ver en lista**
6. **Iterar y mejorar**

---

## Notas Importantes

- **NO usar Inertia/Vue** - Solo Blade + Livewire
- **NO agregar bibliotecas JS externas** (salvo Chart.js si es necesario)
- **Usar componentes Jetstream existentes** cuando sea posible
- **Mobile-first responsive** siempre
- **Tailwind CSS únicamente**
- **Keep it simple** - No over-engineer

---

## Resumen Ejecutivo

### Filosofía: Cliente Primero (Calendly/Acuity Style)

**Experiencia del Cliente:**
- 3 pasos para agendar (Servicio → Fecha → Confirmar)
- Sin login hasta el final
- Mobile-first
- Self-service (cancelar/reagendar sin llamar)

**Experiencia del Profesional:**
- Dashboard con métricas clave
- Calendario visual semanal/mensual
- Quick actions (confirmar/cancelar 1-click)
- Gestión simple de servicios

### Componentes a Implementar (Orden de Prioridad)

**🔴 CRÍTICO (Fase 1 - Flujo Cliente):**
1. `BookingLanding.php` - Landing pública con servicios
2. `BookingWizard.php` - Wizard 3 pasos (calendario + form + confirmación)
3. `BookingManagement.php` - Gestión de cita con token (sin login)

**🟡 IMPORTANTE (Fase 2 - Portal Profesional):**
4. `AdminDashboard.php` - Dashboard con métricas (ya existe controller ✅)
5. `BookingList.php` - Lista de citas con filtros
6. `Calendar.php` - Vista calendario profesional
7. `ServiceManagement.php` - CRUD servicios

**🟢 NICE TO HAVE (Fase 3):**
8. Portal cliente con login (`/my-bookings`)
9. Webhook handlers
10. Notificaciones avanzadas

### Backend Ya Disponible (Reutilizar)
- ✅ `Service` model + factory + seeder
- ✅ `Booking` model (8 estados) + factory + seeder
- ✅ `AvailabilityGenerator` service
- ✅ `PaymentGatewayFactory` (5 gateways)
- ✅ `MetricsCollector` service
- ✅ `HubEventReporter` service
- ✅ Commands: SendReminders, MarkNoShows
- ✅ Tests: 23/23 passing

### Rutas Principales

```php
// Public (no auth)
GET  /book                    → Landing servicios
GET  /book/{service}          → Wizard reserva
POST /book/{service}          → Crear booking
GET  /bookings/{uuid}/manage  → Gestión con token

// Admin (auth + roles)
GET  /admin/dashboard         → Métricas
GET  /admin/bookings          → Lista citas
GET  /admin/calendar          → Calendario
GET  /admin/services          → CRUD servicios

// Client Portal (auth)
GET  /my-bookings             → Mis citas
```

### Tech Stack
- Laravel 12
- Jetstream (Livewire stack)
- Tailwind CSS
- Livewire 3
- Blade components

---

**Estado:** ✅ Plan completado y actualizado con enfoque client-first
**Próxima acción:** Implementar Fase 1 - Landing pública + Wizard de reserva
**Documentado:** 2025-12-14
